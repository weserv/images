#include "http.h"

#include "alloc.h"
#include "http_filter.h"
#include "uri_parser.h"
#include "util.h"

using weserv::api::utils::Status;

namespace weserv::nginx {

namespace {

/**
 * http:// and https:// prefixes used to parse target URL of the HTTP
 * request and identify the protocol.
 */
ngx_str_t http = ngx_string("http://");
#if NGX_HTTP_SSL
ngx_str_t https = ngx_string("https://");
#endif

/**
 * Declarations of response handlers.
 */
ngx_int_t ngx_weserv_upstream_process_status_line(ngx_http_request_t *r);

ngx_int_t ngx_weserv_upstream_process_header(ngx_http_request_t *r);

/**
 * Parses the request URL, identifies the URL scheme and default port.
 */
Status ngx_weserv_upstream_set_url(ngx_pool_t *pool,
                                   ngx_http_upstream_t *upstream, ngx_str_t url,
                                   ngx_array_t *deny, ngx_str_t *host_header,
                                   ngx_str_t *url_path) {
    ngx_url_t parsed_url;
    ngx_memzero(&parsed_url, sizeof(parsed_url));

    // Recognize the URL scheme.
    // Try to recognize a https:// scheme only if NGINX has been compiled with
    // SSL support, because the SSL related fields are not defined otherwise.
    if (url.len > http.len &&
        ngx_strncasecmp(url.data, http.data, http.len) == 0) {
        upstream->schema = http;

        parsed_url.url.data = url.data + http.len;
        parsed_url.url.len = url.len - http.len;
        parsed_url.default_port = 80;

#if NGX_HTTP_SSL
    } else if (url.len > https.len &&
               ngx_strncasecmp(url.data, https.data, https.len) == 0) {
        upstream->schema = https;
        upstream->ssl = 1;

        parsed_url.url.data = url.data + https.len;
        parsed_url.url.len = url.len - https.len;
        parsed_url.default_port = 443;
#endif
    } else {
        return {Status::Code::InvalidUri, "Unable to parse URI",
                Status::ErrorCause::Application};
    }

    // Parse out the URI part of the URL
    parsed_url.uri_part = 1;
    // Do not try to resolve the host name as part of URL parsing
    parsed_url.no_resolve = 1;

    if (ngx_parse_url(pool, &parsed_url) != NGX_OK) {
        return {Status::Code::InvalidUri, "Unable to parse URI",
                Status::ErrorCause::Application};
    }

    if (parsed_url.family == AF_UNIX) {
        return {Status::Code::InvalidUri,
                "Unix domain sockets are not supported",
                Status::ErrorCause::Application};
    }

    // This condition is true if the provided URL is not a domain name, but
    // rather an IP address, for example: ?url=http://46.4.13.221/...
    bool has_ip = parsed_url.addrs != nullptr && parsed_url.addrs[0].sockaddr;

    if (has_ip && deny != nullptr &&
        ngx_cidr_match(parsed_url.addrs[0].sockaddr, deny) == NGX_OK) {
        return {Status::Code::InvalidUri, "IP address blocked by policy",
                Status::ErrorCause::Application};
    }

    // Detect situation where input URL was of the form:
    //     https://domain.com?query_parameters
    // (without a forward slash preceding a question mark), and insert
    // a leading slash to form a valid path: /?query_parameters.
    if (parsed_url.uri.len > 0 && parsed_url.uri.data[0] == '?') {
        auto *p =
            static_cast<u_char *>(ngx_pnalloc(pool, parsed_url.uri.len + 1));
        if (p == nullptr) {
            return {NGX_ERROR, "Out of memory"};
        }

        *p = '/';
        ngx_memcpy(p + 1, parsed_url.uri.data, parsed_url.uri.len);
        parsed_url.uri.len++;
        parsed_url.uri.data = p;
    }

    // Populate the upstream config structure with the parsed URL

    upstream->resolved = new (pool) ngx_http_upstream_resolved_t;
    if (upstream->resolved == nullptr) {
        return {NGX_ERROR, "Out of memory"};
    }

    if (has_ip) {
        // NGINX only returned the parsed IP address, so there is just one
        // address to return. See ngx_parse_url and ngx_inet_addr for details.
        upstream->resolved->sockaddr = parsed_url.addrs[0].sockaddr;
        upstream->resolved->socklen = parsed_url.addrs[0].socklen;
        upstream->resolved->naddrs = 1;
        upstream->resolved->host = parsed_url.addrs[0].name;
    } else {
        upstream->resolved->host = parsed_url.host;
    }

    upstream->resolved->no_port = parsed_url.no_port;
    upstream->resolved->port =
        parsed_url.no_port ? parsed_url.default_port : parsed_url.port;

    // Return Host header and a URL path
    *host_header = parsed_url.host;
    // If the URL contains a port, include it in the host header
    if (!parsed_url.no_port && parsed_url.port != parsed_url.default_port) {
        // Extend the Host header to include ':<port>'
        host_header->len += 1 + parsed_url.port_text.len;
    }
    *url_path = parsed_url.uri;

    return Status::OK;
}

/**
 * Utilities to render HTTP request inside an NGINX buffer.
 * Overloads accepting const std::string&, ngx_str_t and a string literal
 * are available.
 */

inline void append(ngx_buf_t *buf, const std::string &value) {
    buf->last = ngx_cpymem(buf->last, value.c_str(), value.size());
}

inline void append(ngx_buf_t *buf, ngx_str_t value) {
    buf->last = ngx_cpymem(buf->last, value.data, value.len);
}

/**
 * The overload which accepts a string literal.
 * It is templatized on the char array size and returns size of the array - 1
 * (for trailing \0).
 */
template <size_t N>
inline void append(ngx_buf_t *buf, const char (&value)[N]) {
    buf->last = ngx_cpymem(buf->last, value, sizeof(value) - 1);
}

/**
 * HTTP Request upstream handlers.
 *
 * NGINX upstream module is responsible for establishing and maintaining the
 * peer connection, SSH, reading response data. It does not actually construct
 * the request being sent to the upstream, or parse the response.
 *
 * The caller provides the upstream module with several callbacks which create
 * the request buffers, reinitialize state for retries, and parse the response.
 * These callbacks are implemented below.
 */

/**
 * Create request.
 * When NGINX is ready to send the data to the upstream, it calls this handler
 * to create the request buffer.
 * It will compute needed buffer size, allocate the buffer, and create an HTTP
 * request within it, passing it back to NGINX for network communication.
 *
 * Reference: ngx_http_proxy_create_request
 */
ngx_int_t ngx_weserv_upstream_create_request(ngx_http_request_t *r) {
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    ngx_log_debug2(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: ngx_weserv_upstream_create_request (r=%p, ctx=%p)",
                   r, ctx);

    if (ctx == nullptr) {
        return NGX_ERROR;
    }

    // Accumulate buffer size
    size_t buffer_size = 0;

    HTTPRequest *http_request = ctx->request.get();

    ngx_log_debug3(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: creating request (r=%p), %V%V", r,
                   &ctx->host_header, &ctx->url_path);

    // 'GET' followed by a space
    buffer_size += sizeof("GET ") - 1;
    // <URL path> followed by 'HTTP/1.1' and a newline
    buffer_size += ctx->url_path.len + sizeof(" HTTP/1.1\r\n") - 1;
    // 'Host:' header, followed by a newline
    buffer_size += sizeof("Host: ") - 1;
    buffer_size += ctx->host_header.len;
    buffer_size += sizeof("\r\n") - 1;
    buffer_size += sizeof("Connection: Keep-Alive\r\n") - 1;

    // Add sizes of all headers and their values
    for (const auto &[name, value] : http_request->request_headers()) {
        buffer_size += name.size();
        buffer_size += sizeof(": ") - 1;
        buffer_size += value.len;
        buffer_size += sizeof("\r\n") - 1;
    }

    buffer_size += sizeof("\r\n") - 1;  // Newline following the HTTP headers

    // Create a temporary buffer to render the request
    ngx_buf_t *buf = ngx_create_temp_buf(r->pool, buffer_size);
    if (buf == nullptr) {
        return NGX_ERROR;
    }

    // Append an HTTP request line
    append(buf, "GET ");
    append(buf, ctx->url_path);
    append(buf, " HTTP/1.1\r\n");

    // Append the Host and Connection headers
    append(buf, "Host: ");
    append(buf, ctx->host_header);
    append(buf, "\r\n");
    append(buf, "Connection: Keep-Alive\r\n");

    // Append the headers provided by the caller
    for (const auto &[name, value] : http_request->request_headers()) {
        append(buf, name);
        append(buf, ": ");
        append(buf, value);
        append(buf, "\r\n");
    }

    // End request headers, insert newline before the body
    append(buf, "\r\n");

    // Allocate a buffer chain for NGINX
    ngx_chain_t *chain = ngx_alloc_chain_link(r->pool);
    if (chain == nullptr) {
        return NGX_ERROR;
    }

    // We are only sending one buffer
    buf->last_buf = 1;

    chain->buf = buf;
    chain->next = nullptr;

    // Attach the buffer to the request
    r->upstream->request_bufs = chain;
    // r->subrequest_in_memory = 1;

#if NGX_DEBUG
    if (ctx->debug == 1) {
        return NGX_DONE;
    }
#endif

    return NGX_OK;
}

/**
 * A handler NGINX calls when it needs to reinitialize response processing
 * state machine.
 *
 * Reference: ngx_http_proxy_reinit_request
 */
ngx_int_t ngx_weserv_upstream_reinit_request(ngx_http_request_t *r) {
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    ngx_log_debug2(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: ngx_weserv_upstream_reinit_request (r=%p, ctx=%p)",
                   r, ctx);

    if (ctx == nullptr) {
        return NGX_ERROR;
    }

    ngx_http_upstream_t *u = r->upstream;

    // We only reset state to start parsing status line again
    u->process_header = ngx_weserv_upstream_process_status_line;
    u->pipe->input_filter = ngx_weserv_copy_filter;

    ctx->chunked.state = 0;
    r->state = 0;

    return NGX_OK;
}

/**
 * A handler called to parse response status line.
 *
 * NGINX only has one callback for parsing 'header' in general so once we have
 * successfully parsed the HTTP status line, we update the handler to point
 * at the header parsing function. This is a technique used in other
 * implementations of upstream modules.
 *
 * Reference: ngx_http_proxy_process_status_line
 */
ngx_int_t ngx_weserv_upstream_process_status_line(ngx_http_request_t *r) {
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    ngx_log_debug2(
        NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
        "weserv: ngx_weserv_upstream_process_status_line (r=%p, ctx=%p)", r,
        ctx);

    if (ctx == nullptr) {
        return NGX_ERROR;
    }

#if NGX_DEBUG
    // Get the buffer with the response arriving from the upstream server
    ngx_buf_t *buf = &r->upstream->buffer;

    // str is only used in debug mode
    ngx_str_t str = {static_cast<size_t>(buf->last - buf->pos), buf->pos};
    ngx_log_debug1(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "Received (partial) http response:\n%V\n\n", &str);
#endif

    // Parse the status line by calling NGINX helper
    ngx_http_status_t status;
    ngx_memzero(&status, sizeof(status));

    ngx_int_t rc = ngx_http_parse_status_line(r, &r->upstream->buffer, &status);
    if (rc == NGX_AGAIN) {
        // We don't have the whole status line yet
        return rc;
    }

    if (rc == NGX_ERROR) {
        r->upstream->headers_in.connection_close = 1;
        return NGX_OK;
    }

    // We assume that status codes between 300-308 are redirects
    ctx->redirecting = status.code >= 300 && status.code <= 308;

    // Don't parse further if:
    // - a non 200 status code is returned
    // - we're not redirecting
    // - we're not debugging responses
    if (status.code != 200 && !ctx->redirecting
#if NGX_DEBUG
        && ctx->debug == 0
#endif
    ) {
        std::string message = "Response status code: ";
        if (status.start) {
            message += std::string(reinterpret_cast<const char *>(status.start),
                                   status.count);
        }

        // Store the parsed error code
        ctx->response_status = {static_cast<int>(status.code), message,
                                Status::ErrorCause::Upstream};

        return NGX_HTTP_UPSTREAM_INVALID_HEADER;
    }

    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    if (lc->canonical_header) {
        // If we saw a temporary redirect, use that as the canonical one instead
        // of where it points to. This ensures that we only set the canonical to
        // URLs that are considered to be permanent.
        // This should be safe according to RFC 6596 section 3.
        // https://tools.ietf.org/html/rfc6596#section-3
        if (!ctx->saw_temp_redirect) {
            ctx->canonical = ctx->request->url();
        }

        // Flag indicating whenever we saw a temporary redirect in the chain
        ctx->saw_temp_redirect = ctx->saw_temp_redirect || status.code == 302 ||
                                 status.code == 303 || status.code == 307;
    }

    // Store the parsed response status for later
    ctx->response_status = {static_cast<int>(status.code), "",
                            Status::ErrorCause::Upstream};

    if (status.http_version < NGX_HTTP_VERSION_11) {
        r->upstream->headers_in.connection_close = 1;
    }

    // Advance the state machine to parse individual headers next
    r->upstream->process_header = ngx_weserv_upstream_process_header;
    return ngx_weserv_upstream_process_header(r);
}

/**
 * A handler called by NGINX to parse response headers.
 *
 * Reference: ngx_http_proxy_process_header
 */
ngx_int_t ngx_weserv_upstream_process_header(ngx_http_request_t *r) {
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    ngx_log_debug2(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: ngx_weserv_upstream_process_header (r=%p, ctx=%p)",
                   r, ctx);

    if (ctx == nullptr) {
        return NGX_ERROR;
    }

    ngx_http_upstream_t *u = r->upstream;

    for (;;) {
        // Parse an individual header line by calling an NGINX helper
        ngx_int_t rc = ngx_http_parse_header_line(r, &u->buffer, 1);

        if (rc == NGX_OK) {
            // A header line has been parsed successfully

            ngx_str_t name = {
                static_cast<size_t>(r->header_name_end - r->header_name_start),
                r->header_name_start};
            ngx_strlow(name.data, name.data, name.len);
            ngx_str_t value = {
                static_cast<size_t>(r->header_end - r->header_start),
                r->header_start};

            ngx_log_debug2(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                           "weserv header: \"%V: %V\"", &name, &value);

            // Check if the header was "Content-Length"
            static ngx_str_t content_length = ngx_string("Content-Length");
            if (name.len == content_length.len &&
                ngx_strncasecmp(name.data, content_length.data,
                                content_length.len) == 0) {
                // Store the content length on the incoming headers object
                u->headers_in.content_length_n =
                    ngx_atoof(value.data, value.len);
            }

            // Check if the header was "Transfer-Encoding: chunked"
            static ngx_str_t transfer_encoding =
                ngx_string("Transfer-Encoding");
            static ngx_str_t chunked = ngx_string("chunked");
            if (name.len == transfer_encoding.len &&
                ngx_strncasecmp(name.data, transfer_encoding.data,
                                transfer_encoding.len) == 0 &&
                value.len == chunked.len &&
                ngx_strncasecmp(value.data, chunked.data, chunked.len) == 0) {
                // Store the chunked flag
                u->headers_in.chunked = 1;
            }

            // Check if there was a redirection URI
            static ngx_str_t location = ngx_string("Location");
            if (ctx->redirecting && name.len == location.len &&
                ngx_strncasecmp(name.data, location.data, location.len) == 0) {
                ngx_str_t absolute_url = value;

                if (!has_valid_scheme(value)) {
                    // Relative URIs are allowed in the Location header, see:
                    // https://tools.ietf.org/html/rfc7231#section-7.1.2
                    (void)concat_url(r->pool, ctx->request->url(), value,
                                     &absolute_url);
                }

                // Parse the absolute redirection URI
                (void)parse_url(r->pool, absolute_url, &ctx->location);
            }

            continue;
        }

        if (rc == NGX_HTTP_PARSE_HEADER_DONE) {
            // A whole header has been parsed successfully
            ngx_log_debug0(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                           "weserv header done");
#if NGX_DEBUG
            if (ctx->debug == 2) {
                u->headers_in.content_length_n =
                    u->buffer.pos - u->buffer.start;

                u->buffer.end = u->buffer.pos;
                u->buffer.pos = u->buffer.start;

                // Don't need to store the chunked flag
                u->headers_in.chunked = 0;
            } else if (u->headers_in.chunked) {
#else
            if (u->headers_in.chunked) {
#endif
                // Clear content length if response is chunked
                u->headers_in.content_length_n = -1;
            }

            auto *lc = static_cast<ngx_weserv_loc_conf_t *>(
                ngx_http_get_module_loc_conf(r, ngx_weserv_module));

            if (lc->max_size > 0 && u->headers_in.content_length_n >
                                        static_cast<off_t>(lc->max_size)) {
                ngx_log_error(
                    NGX_LOG_ERR, r->connection->log, 0,
                    "upstream intended to send too large body: %O bytes",
                    u->headers_in.content_length_n);

                ctx->response_status = {
                    413,
                    "The image is too large to be downloaded. "
                    "Max image size: " +
                        std::to_string(lc->max_size) + " bytes",
                    Status::ErrorCause::Upstream};

                return NGX_HTTP_UPSTREAM_INVALID_HEADER;
            }

            if (ctx->redirecting) {
                return NGX_HTTP_MOVED_PERMANENTLY;
            }

            return NGX_OK;
        }

        if (rc == NGX_AGAIN) {
            return NGX_AGAIN;
        }

        // rc == NGX_HTTP_PARSE_INVALID_HEADER

        // NGINX versions prior to 1.21.1 didn't set the r->header_end
        // pointer correctly in some cases, making it impossible to log the
        // invalid header
#if defined(nginx_version) && nginx_version >= 1021001
        ngx_log_error(NGX_LOG_ERR, r->connection->log, 0,
                      "upstream sent invalid header: \"%*s\\x%02xd...\"",
                      r->header_end - r->header_name_start,
                      r->header_name_start, *r->header_end);
#else
        ngx_log_error(NGX_LOG_ERR, r->connection->log, 0,
                      "upstream sent invalid header");
#endif

        return NGX_HTTP_UPSTREAM_INVALID_HEADER;
    }
}

/**
 * An abort handler -- apparently this is never called by NGINX so we only log.
 *
 * Reference: ngx_http_proxy_abort_request
 */
void ngx_weserv_upstream_abort_request(ngx_http_request_t *r) {
    ngx_log_error(NGX_LOG_DEBUG, r->connection->log, 0,
                  "weserv: request aborted");
}

/**
 * A finalize handler. Called by NGINX when request is complete (success)
 * or on error, for example connection error, timeout, etc.
 *
 * Reference: ngx_http_proxy_finalize_request
 */
void ngx_weserv_upstream_finalize_request(ngx_http_request_t *r, ngx_int_t rc) {
    if (r == nullptr) {
        return;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    ngx_log_debug3(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: finalizing request r=%p, rc=%d, ctx=%p", r, rc,
                   ctx);

    if (ctx == nullptr) {
        return;
    }

    ngx_log_debug2(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "ngx_weserv_upstream_finalize_request called: %V%V",
                   &ctx->host_header, &ctx->url_path);

    if (rc != NGX_OK) {
        if (ctx->response_status.ok()) {
            ctx->response_status = {static_cast<int>(rc),
                                    "Failed to connect to server",
                                    Status::ErrorCause::Upstream};
        }

        rc = NGX_ERROR;
    } else if (ctx->redirecting) {
        // Swap the initial HTTP request out
        std::unique_ptr<HTTPRequest> request;
        request.swap(ctx->request);

        ngx_str_t referer = request->url();

        // Increase redirect counter
        ++(*request);

        // Check redirection loop
        if (ctx->location.len == referer.len &&
            ngx_strncasecmp(ctx->location.data, referer.data, referer.len) ==
                0) {
            ctx->response_status = {310,
                                    "Will not follow a redirection to itself",
                                    Status::ErrorCause::Upstream};

            rc = NGX_ERROR;
        } else if (request->redirect_count() >= request->max_redirects()) {
            ctx->response_status = {
                310,
                "Will not follow more than " +
                    std::to_string(request->max_redirects()) + " redirects",
                Status::ErrorCause::Upstream};

            rc = NGX_ERROR;
        } else {  // Redirect if there are redirects left
            // Set new redirection URI and referer
            request->set_url(ctx->location);
            request->set_header("Referer", referer);

            ngx_log_debug1(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                           "Redirect request, %d redirects left",
                           request->max_redirects() -
                               request->redirect_count());

            // Swap the initial HTTP request out to the next iteration
            ctx->request = std::move(request);

            rc = ngx_weserv_send_http_request(r, ctx);
        }
    }

    if (rc == NGX_ERROR) {
        // Reset the redirect flag if an error occurs
        ctx->redirecting = 0;
    }
}

/**
 * A resolve handler. Called by NGINX when a hostname needs to be resolved.
 */
void ngx_weserv_upstream_resolve_handler(ngx_resolver_ctx_t *resolver_ctx) {
    auto *r = static_cast<ngx_http_request_t *>(resolver_ctx->data);

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr) {
        return;
    }

    // Treat refused DNS queries as blocked (aligns with the "always_refuse"
    // setting in Unbound)
    if (resolver_ctx->state == NGX_RESOLVE_REFUSED) {
        ctx->response_status = {Status::Code::InvalidUri,
                                "Domain or TLD blocked by policy",
                                Status::ErrorCause::Application};
    }

    ctx->original_resolver(resolver_ctx);
}

/**
 * Initializes the upstream data structures which NGINX upstream module uses to
 * call the server.
 */
Status initialize_upstream_request(ngx_http_request_t *r,
                                   ngx_weserv_upstream_ctx_t *ctx) {
    // Create the NGINX upstream structures
    if (ngx_http_upstream_create(r) != NGX_OK) {
        return {NGX_ERROR, "Out of memory"};
    }

    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    ngx_http_upstream_t *u = r->upstream;

    // Parse the URL provided by the caller
    Status status =
        ngx_weserv_upstream_set_url(r->pool, u, ctx->request->url(), lc->deny,
                                    &ctx->host_header, &ctx->url_path);
    if (!status.ok()) {
        return status;
    }

    u->output.tag = static_cast<ngx_buf_tag_t>(&ngx_weserv_module);

    u->conf = &lc->upstream_conf;
    u->buffering = lc->upstream_conf.buffering;

    // Set up the upstream handlers which create the request HTTP buffers, and
    // process the response data as the upstream module reads it from the wire
    u->pipe = new (r->pool) ngx_event_pipe_t;
    if (u->pipe == nullptr) {
        return {NGX_ERROR, "Out of memory"};
    }

    // The request filter context is the request object (ngx_request_t)
    u->pipe->input_ctx = r;
    u->pipe->input_filter = ngx_weserv_copy_filter;

    u->input_filter_init = ngx_weserv_input_filter_init;

    // No need to set non buffered copy filters; we are always buffering
    // responses
    // u->input_filter = ngx_weserv_non_buffered_copy_filter;
    // u->input_filter_ctx = r;

    u->create_request = ngx_weserv_upstream_create_request;
    u->reinit_request = ngx_weserv_upstream_reinit_request;
    u->process_header = ngx_weserv_upstream_process_status_line;
    u->abort_request = ngx_weserv_upstream_abort_request;
    u->finalize_request = ngx_weserv_upstream_finalize_request;

    u->accel = 1;

    return Status::OK;
}

}  // namespace

ngx_int_t ngx_weserv_send_http_request(ngx_http_request_t *r,
                                       ngx_weserv_upstream_ctx_t *ctx) {
    ngx_log_debug1(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: sending http request: %V", &ctx->request->url());

    Status status = initialize_upstream_request(r, ctx);

    if (!status.ok()) {
        ctx->response_status = status;

        return NGX_ERROR;
    }

    ngx_log_debug1(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv: calling ngx_http_upstream_init(%p)", r);

    r->main->count++;

    // Initiate the upstream connection by calling NGINX upstream
    ngx_http_upstream_init(r);

    // Override the upstream resolve handler to our own to ensure
    // a custom error message is provided for refused DNS queries
    if (r->upstream->resolved->ctx != nullptr) {
        ctx->original_resolver = r->upstream->resolved->ctx->handler;

        r->upstream->resolved->ctx->handler =
            ngx_weserv_upstream_resolve_handler;
    }

    return NGX_DONE;
}

}  // namespace weserv::nginx
