#include "uri_parser.h"

#include <algorithm>
#include <functional>
#include <string>
#include <vector>

namespace weserv::nginx {

/**
 * Parameters for Punycode, see:
 * http://tools.ietf.org/html/rfc3492#section-5
 */
constexpr uint32_t BASE = 36;
constexpr uint32_t TMIN = 1;
constexpr uint32_t TMAX = 26;
constexpr uint32_t SKEW = 38;
constexpr uint32_t DAMP = 700;
constexpr uint32_t INITIAL_BIAS = 72;
constexpr uint32_t INITIAL_N = 128;

/**
 * Array of skip-bytes-per-initial character.
 */
static const u_char UTF8_SKIP[256] = {
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2,
    2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3,
    4, 4, 4, 4, 4, 4, 4, 4, 5, 5, 5, 5, 6, 6, 1, 1};

/**
 * Skips to the next character in a UTF-8 string.
 */
#define utf8_next_char(p) ((p) + UTF8_SKIP[*(p)])

/**
 * RFC 3490, section 3.1 says '.', 0x3002, 0xFF0E, and 0xFF61 count as
 * label-separating dots. @str must be '\0'-terminated.
 */
#define idna_is_dot(str)                                                       \
    ((static_cast<u_char>((str)[0]) == '.') ||                                 \
     (static_cast<u_char>((str)[0]) == 0xE3 &&                                 \
      static_cast<u_char>((str)[1]) == 0x80 &&                                 \
      static_cast<u_char>((str)[2]) == 0x82) ||                                \
     (static_cast<u_char>((str)[0]) == 0xEF &&                                 \
      static_cast<u_char>((str)[1]) == 0xBC &&                                 \
      static_cast<u_char>((str)[2]) == 0x8E) ||                                \
     (static_cast<u_char>((str)[0]) == 0xEF &&                                 \
      static_cast<u_char>((str)[1]) == 0xBD &&                                 \
      static_cast<u_char>((str)[2]) == 0xA1))

/**
 * encode_digit(d) returns the basic code point whose value
 * (when used for representing integers) is d, which needs to be in
 * the range 0 to BASE-1.
 */
char encode_digit(uint32_t d) {
    // 0..25 map to ASCII a..z or A..Z
    // 26..35 map to ASCII 0..9
    if (d < 26) {
        return static_cast<char>(d) + 97;
    }
    return static_cast<char>(d) + 22;
}

/**
 * Punycode bias adaptation algorithm, RFC 3492 section 6.1.
 */
uint32_t adapt(uint32_t delta, uint32_t n_points, bool is_first) {
    // scale back, then increase delta
    delta /= is_first ? DAMP : 2;
    delta += delta / n_points;

    constexpr uint32_t s = BASE - TMIN;
    constexpr uint32_t t = (s * TMAX) / 2;

    uint32_t k = 0;
    for (; delta > t; k += BASE) {
        delta /= s;
    }

    const uint32_t a = (BASE - TMIN + 1) * delta;
    const uint32_t b = (delta + SKEW);

    return k + (a / b);
}

/**
 * Calculate the bias threshold to fall between TMIN and TMAX.
 */
uint32_t calculate_threshold(uint32_t k, uint32_t bias) {
    if (k <= bias /* + TMIN*/) {  // + TMIN not needed
        return TMIN;
    } else if (k >= bias + TMAX) {
        return TMAX;
    }
    return k - bias;
}

/**
 * Convenience function for sorting (in descending order)
 * and removing duplicate elements.
 */
template <typename T>
void sort_uniq(std::vector<T> &container) {
    std::sort(container.begin(), container.end(), std::greater<T>());
    auto last = std::unique(container.begin(), container.end());
    container.erase(last, container.end());
}

/**
 * Punycode encoder, RFC 3492 section 6.3. The algorithm is
 * sufficiently bizarre that it's not really worth trying to explain
 * here.
 */
ngx_int_t punycode_encode(u_char *input, size_t input_length,
                          std::string *output) {
    std::vector<uint32_t> all;
    std::vector<uint32_t> non_basic;

    // Handle the basic code points
    u_char *p = input;
    u_char *last = p + input_length;

    size_t len;
    for (len = 0; p < last; len++) {
        u_char c = *p;

        if (c < 0x80) {
            *output += c;
            all.push_back(c);

            p++;
            continue;
        }

        uint32_t n = ngx_utf8_decode(&p, last - p);

        if (n > 0x10ffff) {
            // invalid UTF-8
            return NGX_ERROR;
        }

        non_basic.push_back(n);
        all.push_back(n);
    }

    // h is the number of code points that have been handled, b is the
    // number of basic code points.
    size_t h = output->size();
    size_t b = h;

    // Append a delimiter to the output, unless it's empty
    if (b > 0) {
        *output += '-';
    }

    // Initialize the state
    uint32_t n = INITIAL_N;
    uint32_t bias = INITIAL_BIAS;
    uint32_t delta = 0;

    sort_uniq(non_basic);

    // Main encoding loop
    for (/* void */; h < len; ++n, ++delta) {
        uint32_t m = non_basic.back();
        non_basic.pop_back();

        // Increase delta enough to advance the decoder's
        // <n,i> state to <m,0>
        delta += (m - n) * (h + 1);
        n = m;

        for (const auto &c : all) {
            // Guard against overflow
            if (c < n && ++delta == 0) {
                return NGX_ERROR;
            }

            if (c == n) {
                // Represent delta as a generalized variable-length integer
                for (uint32_t q = delta, k = BASE; /* void */; k += BASE) {
                    uint32_t t = calculate_threshold(k, bias);
                    if (q < t) {
                        *output += encode_digit(q);
                        break;
                    }

                    *output += encode_digit(t + (q - t) % (BASE - t));
                    q = (q - t) / (BASE - t);
                }

                bias = adapt(delta, h + 1, b == h);
                delta = 0;
                ++h;
            }
        }
    }

    output->insert(0, "xn--");

    return NGX_OK;
}

/**
 * From ngx_escape_uri, modified to not escape percent signs,
 * and reserved characters according to RFC 3986 section 2.2 (January 2005).
 * See: https://github.com/weserv/images/issues/144
 */
uintptr_t escape_path(u_char *dst, u_char *src, size_t size) {
    static u_char hex[] = "0123456789ABCDEF";

    // Per RFC 3986 only the following chars are allowed in URIs unescaped:
    //
    // unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
    // gen-delims    = ":" / "/" / "?" / "#" / "[" / "]" / "@"
    // sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
    //               / "*" / "+" / "," / ";" / "="
    //
    // And "%" can appear as a part of escaping itself. The following
    // characters are not allowed and need to be escaped: %00-%1F, %7F-%FF,
    // " ", """, "<", ">", "\", "^", "`", "{", "|", "}".

    // clang-format off
    static uint32_t escape[] = {
        0xffffffff, // 1111 1111 1111 1111  1111 1111 1111 1111

                    // ?>=< ;:98 7654 3210  /.-, +*)( '&%$ #"! 
        0x50000005, // 0101 0000 0000 0000  0000 0000 0000 0101

                    // _^]\ [ZYX WVUT SRQP  ONML KJIH GFED CBA@
        0x50000000, // 0101 0000 0000 0000  0000 0000 0000 0000

                    //  ~}| {zyx wvut srqp  onml kjih gfed cba`
        0xb8000001, // 1011 1000 0000 0000  0000 0000 0000 0001

        0xffffffff, // 1111 1111 1111 1111  1111 1111 1111 1111
        0xffffffff, // 1111 1111 1111 1111  1111 1111 1111 1111
        0xffffffff, // 1111 1111 1111 1111  1111 1111 1111 1111
        0xffffffff  // 1111 1111 1111 1111  1111 1111 1111 1111
    };
    // clang-format on

    if (dst == nullptr) {
        // Find the number of the characters to be escaped
        ngx_uint_t n = 0;

        while (size) {
            if (escape[*src >> 5] & (1U << (*src & 0x1f))) {
                n++;
            }
            src++;
            size--;
        }

        return n;
    }

    while (size) {
        if (escape[*src >> 5] & (1U << (*src & 0x1f))) {
            *dst++ = '%';
            *dst++ = hex[*src >> 4];
            *dst++ = hex[*src & 0xf];
            src++;

        } else {
            *dst++ = *src++;
        }
        size--;
    }

    return reinterpret_cast<uintptr_t>(dst);
}

ngx_int_t concat_url(ngx_pool_t *pool, const ngx_str_t &base,
                     const ngx_str_t &relative, ngx_str_t *output) {
    // Try to append this new path to the old URL to the right of the host part

    // We must make our own copy of the URL to play with, as it may
    // point to read-only data
    auto *url_clone = static_cast<u_char *>(ngx_pnalloc(pool, base.len + 1));
    if (url_clone == nullptr) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

    ngx_memcpy(url_clone, base.data, base.len);

    // Ensure null-terminated string
    url_clone[base.len] = '\0';

    // protsep points to the start of the host name
    u_char *protsep = ngx_strstrn(url_clone, const_cast<char *>("//"), 2 - 1);
    if (protsep == nullptr) {
        protsep = url_clone;
    } else {
        protsep += 2;  // Pass the slashes
    }

    u_char *p = relative.data;
    u_char *last = p + relative.len;

    if (relative.data[0] != '/') {
        int level = 0;

        // First we need to find out if there's a ?-letter in the URL,
        // and cut it and the right-side of that off
        u_char *pathsep = (u_char *)ngx_strchr(protsep, '?');
        if (pathsep != nullptr) {
            *pathsep = '\0';
        }

        // We have a relative path to append to the last slash if there's one
        // available, or if the new URL is just a query string (starts with a
        // '?') we append the new one at the end of the entire currently worked
        // out URL
        if (p[0] != '?') {
            pathsep = (u_char *)strrchr((const char *)protsep, '/');
            if (pathsep != nullptr) {
                *pathsep = '\0';
            }
        }

        // Check if there's any slash after the host name, and if so, remember
        // that position instead
        pathsep = (u_char *)ngx_strchr(protsep, '/');
        if (pathsep != nullptr) {
            protsep = pathsep + 1;
        } else {
            protsep = nullptr;
        }

        // Now deal with one "./" or any amount of "../" and act accordingly
        if (p[0] == '.' && p[1] == '/') {
            p += 2;  // Just skip the "./"
        }

        while (p[0] == '.' && p[1] == '.' && p[2] == '/') {
            level++;
            p += 3;  // Pass the "../"
        }

        if (protsep != nullptr) {
            while (level--) {
                // Cut off one more level from the right of the original URL
                pathsep = (u_char *)strrchr((const char *)protsep, '/');
                if (pathsep != nullptr) {
                    *pathsep = '\0';
                } else {
                    *protsep = '\0';
                    break;
                }
            }
        }
    } else if (relative.data[1] == '/') {
        // The new URL starts with //, just keep the protocol part from the
        // original one
        *protsep = '\0';
        // We keep the slashes from the original, so we skip the new ones
        p = &relative.data[2];
    } else {
        // Cut off the original URL from the first slash, or deal with URLs
        // without slash
        u_char *pathsep = (u_char *)ngx_strchr(protsep, '/');
        if (pathsep != nullptr) {
            // When people use badly formatted URLs, such as
            // "http://www.url.com?dir=/home/daniel" we must not use the
            // first slash, if there's a ?-letter before it!
            u_char *sep = (u_char *)ngx_strchr(protsep, '?');
            if (sep != nullptr && sep < pathsep) {
                pathsep = sep;
            }
            *pathsep = '\0';
        } else {
            // There was no slash. Now, since we might be operating on a
            // badly formatted URL, such as "http://www.url.com?id=2380"
            // which doesn't use a slash separator as it is supposed to, we
            // need to check for a ?-letter as well!
            pathsep = (u_char *)ngx_strchr(protsep, '?');
            if (pathsep != nullptr) {
                *pathsep = '\0';
            }
        }
    }

    size_t newlen = ngx_strnlen(p, last - p);
    size_t urllen = ngx_strlen(url_clone);

    // + 1 for possible slash
    output->data =
        static_cast<u_char *>(ngx_pnalloc(pool, urllen + newlen + 1));
    if (output->data == nullptr) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

    // Copy over the root url part
    u_char *o = ngx_cpymem(output->data, url_clone, urllen);
    output->len = urllen;

    // Check if we need to append a slash
    if (p[0] != '/' && p[0] != '?' &&
        (protsep == nullptr || *protsep != '\0')) {
        *o++ = '/';
        output->len++;
    }

    ngx_memcpy(o, p, newlen);
    output->len += newlen;

    return NGX_OK;
}

ngx_int_t parse_url(ngx_pool_t *pool, ngx_str_t &uri, ngx_str_t *output) {
    if (uri.len < sizeof("i.nl") - 1) {
        return NGX_ERROR;
    }

    u_char *src = uri.data;
    auto *dst = static_cast<u_char *>(ngx_pnalloc(pool, uri.len));
    if (dst == nullptr) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

    uri.data = dst;

    ngx_unescape_uri(&dst, &src, uri.len, 0);

    uri.len = dst - uri.data;

    u_char *ref = uri.data;
    u_char *last = dst;
    ngx_str_t protocol;

    if (uri.len > 7 && ngx_strncasecmp(ref, (u_char *)"http://", 7) == 0) {
        protocol = ngx_string("http://");
        ref += 7;
#if NGX_HTTP_SSL
    } else if (uri.len > 8 &&
               ngx_strncasecmp(ref, (u_char *)"https://", 8) == 0) {
        // Check for HTTPS origin hosts
        protocol = ngx_string("https://");
        ref += 8;
    } else if (uri.len > 4 && ngx_strncasecmp(ref, (u_char *)"ssl:", 4) == 0) {
        protocol = ngx_string("https://");
        ref += 4;
    } else if (uri.len > 2 && ngx_strncasecmp(ref, (u_char *)"//", 2) == 0) {
        // If the URI is schemaless (i.e. //example.com), prepend 'https:'
        protocol = ngx_string("https://");
        ref += 2;
#endif
    } else {
        // If the uri is given without protocol (i.e. example.com), prepend
        // 'http://'
        protocol = ngx_string("http://");
    }

    // uri.find_first_not_of("/");
    while (ref < last && *ref == '/') {
        ++ref;
    }

    // uri.find_first_of("/?")
    u_char *path = ngx_strlchr(ref, last, '/');
    if (path == nullptr) {
        path = ngx_strlchr(ref, last, '?');
    }

    if (path == nullptr) {
        path = last;
    }

    // Remove the fragment part of the path. Per RFC 3986, this is always the
    // last part of the URI. We are looking for the first '#' so that we deal
    // gracefully with non-conformant URI such as http://example.com#foo#bar
    u_char *fragment = ngx_strlchr(path, last, '#');
    if (fragment != nullptr) {
        last = fragment;
    }

    size_t path_length = static_cast<size_t>(last - path);

    // Note: each escaped character is replaced by 3 characters
    uintptr_t escaped_length =
        path_length > 0 ? 2 * escape_path(nullptr, path, path_length) : 1;

    size_t domain_length = static_cast<size_t>(path - ref);

    // Guard against overflow. 253 characters is the maximum length of full
    // domain name, including dots.
    if (domain_length > 253) {
        return NGX_ERROR;
    }

    output->data = static_cast<u_char *>(
        ngx_pnalloc(pool, protocol.len + 253 + path_length + escaped_length));
    if (output->data == nullptr) {
        return NGX_ERROR;
    }

    u_char *o = ngx_cpymem(output->data, protocol.data, protocol.len);
    output->len = protocol.len;

    u_char *label = ref;
    u_char *p;

    do {
        bool unicode = false;

        for (p = label; p < path && !idna_is_dot(p); ++p) {
            if (*p > 0x80) {
                unicode = true;
            }
        }

        size_t llen = p - label;

        // Each label may contain up to 63 characters
        if (llen > 63) {
            return NGX_ERROR;
        }

        if (unicode) {
            std::string idn_label;
            if (punycode_encode(label, llen, &idn_label) != NGX_OK) {
                return NGX_ERROR;
            }

            domain_length = output->len - protocol.len + idn_label.size();

            // Punycoded labels still have to be <= 63 characters long.
            // Also check if we exceed the 253 characters limit,
            // because labels that are puny-encoded might be longer.
            if (idn_label.size() > 63 || domain_length > 253) {
                return NGX_ERROR;
            }

            o = ngx_cpymem(o, idn_label.data(), idn_label.size());
            output->len += idn_label.size();
        } else {
            o = ngx_cpymem(o, label, llen);
            output->len += llen;
        }

        label += llen;
        if (label < path) {
            label = utf8_next_char(label);
        }

        // Append dot between labels
        if (label < path) {
            *o++ = '.';
            output->len++;
        }
    } while (label < path);

    if (path_length > 0) {
        (void)escape_path(o, path, path_length);
    } else {
        // Add a leading slash to form a valid path
        *o++ = '/';
    }

    output->len += path_length + escaped_length;

    return NGX_OK;
}

}  // namespace weserv::nginx
