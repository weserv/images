#include "header.h"

#include "util.h"

#include <algorithm>

namespace weserv::nginx {

const ngx_str_t CONTENT_DISPOSITION = ngx_string("Content-Disposition");
constexpr u_char CONTENT_DISPOSITION_LOWCASE[] = "content-disposition";

const ngx_str_t LINK = ngx_string("Link");
constexpr u_char LINK_LOWCASE[] = "link";

ngx_int_t set_expires_header(ngx_http_request_t *r, time_t max_age) {
    ngx_table_elt_t *e = r->headers_out.expires;
    if (e == nullptr) {
        e = static_cast<ngx_table_elt_t *>(
            ngx_list_push(&r->headers_out.headers));
        if (e == nullptr) {
            return NGX_ERROR;
        }

        r->headers_out.expires = e;
#if defined(nginx_version) && nginx_version >= 1023000
        e->next = nullptr;
#endif

        e->hash = 1;
        ngx_str_set(&e->key, "Expires");
    }

    size_t len = sizeof("Mon, 28 Sep 1970 06:00:00 GMT");
    e->value.len = len - 1;

#if defined(nginx_version) && nginx_version >= 1023000
    ngx_table_elt_t *cc = r->headers_out.cache_control;

    if (cc == nullptr) {
#else
    ngx_table_elt_t **ccp =
        static_cast<ngx_table_elt_t **>(r->headers_out.cache_control.elts);
    ngx_table_elt_t *cc;

    if (ccp == nullptr) {
        ngx_int_t rc = ngx_array_init(&r->headers_out.cache_control, r->pool, 1,
                                      sizeof(ngx_table_elt_t *));
        if (rc != NGX_OK) {
            return NGX_ERROR;
        }

#endif
        cc = static_cast<ngx_table_elt_t *>(
            ngx_list_push(&r->headers_out.headers));
        if (cc == nullptr) {
            e->hash = 0;
            return NGX_ERROR;
        }

#if defined(nginx_version) && nginx_version >= 1023000
        r->headers_out.cache_control = cc;
        cc->next = nullptr;
#endif

        cc->hash = 1;
        ngx_str_set(&cc->key, "Cache-Control");

#if defined(nginx_version) && nginx_version >= 1023000
    } else {
        for (cc = cc->next; cc; cc = cc->next) {
            cc->hash = 0;
        }

        cc = r->headers_out.cache_control;
        cc->next = nullptr;
#else
        ccp = static_cast<ngx_table_elt_t **>(
            ngx_array_push(&r->headers_out.cache_control));
        if (ccp == nullptr) {
            return NGX_ERROR;
        }

        *ccp = cc;
    } else {
        for (ngx_uint_t i = 1; i < r->headers_out.cache_control.nelts; ++i) {
            ccp[i]->hash = 0;
        }

        cc = ccp[0];
#endif
    }

    e->value.data = static_cast<u_char *>(ngx_pnalloc(r->pool, len));
    if (e->value.data == nullptr) {
        e->hash = 0;
        cc->hash = 0;
        return NGX_ERROR;
    }

    time_t expires_time = ngx_time() + max_age;

    ngx_http_time(e->value.data, expires_time);

    cc->value.data = static_cast<u_char *>(
        ngx_pnalloc(r->pool, sizeof("public, max-age=") + NGX_TIME_T_LEN + 1));
    if (cc->value.data == nullptr) {
        cc->hash = 0;
        return NGX_ERROR;
    }

    cc->value.len = ngx_sprintf(cc->value.data, "public, max-age=%T", max_age) -
                    cc->value.data;

    return NGX_OK;
}

ngx_int_t set_content_disposition_header(ngx_http_request_t *r,
                                         const std::string &extension) {
    bool is_valid = false;

    ngx_str_t filename;
    if (ngx_http_arg(r, (u_char *)"filename", 8, &filename) == NGX_OK) {
        // https://tools.ietf.org/html/rfc2183
        is_valid = filename.len != 0 && filename.len <= 78 &&
                   std::all_of(filename.data, filename.data + filename.len,
                               [](u_char c) { return std::isalnum(c); });
    }

    if (!is_valid) {
        filename = ngx_string("image");
    }

    size_t prefix_size = sizeof("inline; filename=") - 1;
    size_t header_size = prefix_size + filename.len + extension.size();

    auto *p = static_cast<u_char *>(ngx_pnalloc(r->pool, header_size));
    if (p == nullptr) {
        return NGX_ERROR;
    }

    u_char *o = ngx_cpymem(p, "inline; filename=", prefix_size);
    o = ngx_cpymem(o, filename.data, filename.len);
    o = ngx_cpymem(o, extension.data(), extension.size());

    auto *h =
        static_cast<ngx_table_elt_t *>(ngx_list_push(&r->headers_out.headers));
    if (h == nullptr) {
        return NGX_ERROR;
    }

    h->key = CONTENT_DISPOSITION;
    h->lowcase_key = const_cast<u_char *>(CONTENT_DISPOSITION_LOWCASE);
    h->hash = ngx_hash_key(const_cast<u_char *>(CONTENT_DISPOSITION_LOWCASE),
                           sizeof(CONTENT_DISPOSITION_LOWCASE) - 1);

    h->value.data = p;
    h->value.len = header_size;

    return NGX_OK;
}

ngx_int_t set_location_header(ngx_http_request_t *r, ngx_str_t *value) {
    r->headers_out.location =
        static_cast<ngx_table_elt_t *>(ngx_list_push(&r->headers_out.headers));
    if (r->headers_out.location == nullptr) {
        return NGX_ERROR;
    }

    r->headers_out.location->hash = 1;
#if defined(nginx_version) && nginx_version >= 1023000
    r->headers_out.location->next = nullptr;
#endif
    ngx_str_set(&r->headers_out.location->key, "Location");

    r->headers_out.location->value = *value;

    return NGX_OK;
}

ngx_int_t set_link_header(ngx_http_request_t *r, const ngx_str_t &url) {
    if (url.len == 0) {
        return NGX_OK;
    }

    size_t prefix_size = sizeof("<") - 1;
    size_t suffix_size = sizeof(">; rel=\"canonical\"") - 1;
    size_t header_size = prefix_size + url.len + suffix_size;

    auto *p = static_cast<u_char *>(ngx_pnalloc(r->pool, header_size));
    if (p == nullptr) {
        return NGX_ERROR;
    }

    u_char *o = ngx_cpymem(p, "<", prefix_size);
    o = ngx_cpymem(o, url.data, url.len);
    o = ngx_cpymem(o, ">; rel=\"canonical\"", suffix_size);

    auto *h =
        static_cast<ngx_table_elt_t *>(ngx_list_push(&r->headers_out.headers));
    if (h == nullptr) {
        return NGX_ERROR;
    }

    h->key = LINK;
    h->lowcase_key = const_cast<u_char *>(LINK_LOWCASE);
    h->hash = ngx_hash_key(const_cast<u_char *>(LINK_LOWCASE),
                           sizeof(LINK_LOWCASE) - 1);

    h->value.data = p;
    h->value.len = header_size;

    return NGX_OK;
}

}  // namespace weserv::nginx
