#include "header.h"

#include "util.h"

namespace weserv {
namespace nginx {

const ngx_str_t CONTENT_DISPOSITION = ngx_string("Content-Disposition");
const u_char CONTENT_DISPOSITION_LOWCASE[] = "content-disposition";

const ngx_str_t LOCATION = ngx_string("Location");
const u_char LOCATION_LOWCASE[] = "location";

ngx_int_t set_expires_header(ngx_http_request_t *r, time_t max_age) {
    ngx_table_elt_t *e = r->headers_out.expires;
    if (e == nullptr) {
        e = reinterpret_cast<ngx_table_elt_t *>(
            ngx_list_push(&r->headers_out.headers));
        if (e == nullptr) {
            return NGX_ERROR;
        }

        r->headers_out.expires = e;

        e->hash = 1;
        ngx_str_set(&e->key, "Expires");
    }

    size_t len = sizeof("Mon, 28 Sep 1970 06:00:00 GMT");
    e->value.len = len - 1;

    ngx_table_elt_t **ccp =
        reinterpret_cast<ngx_table_elt_t **>(r->headers_out.cache_control.elts);
    ngx_table_elt_t *cc;

    if (ccp == nullptr) {
        ngx_int_t rc = ngx_array_init(&r->headers_out.cache_control, r->pool, 1,
                                      sizeof(ngx_table_elt_t *));
        if (rc != NGX_OK) {
            return NGX_ERROR;
        }

        cc = reinterpret_cast<ngx_table_elt_t *>(
            ngx_list_push(&r->headers_out.headers));
        if (cc == nullptr) {
            return NGX_ERROR;
        }

        cc->hash = 1;
        ngx_str_set(&cc->key, "Cache-Control");

        ccp = reinterpret_cast<ngx_table_elt_t **>(
            ngx_array_push(&r->headers_out.cache_control));
        if (ccp == nullptr) {
            return NGX_ERROR;
        }

        *ccp = cc;
    } else {
        for (ngx_uint_t i = 0; i < r->headers_out.cache_control.nelts; ++i) {
            ccp[i]->hash = 0;
        }

        cc = ccp[0];
    }

    e->value.data = reinterpret_cast<u_char *>(ngx_pnalloc(r->pool, len));
    if (e->value.data == nullptr) {
        return NGX_ERROR;
    }

    time_t expires_time = ngx_time() + max_age;

    ngx_http_time(e->value.data, expires_time);

    cc->value.data = reinterpret_cast<u_char *>(
        ngx_pnalloc(r->pool, sizeof("public, max-age=") + NGX_TIME_T_LEN + 1));
    if (cc->value.data == nullptr) {
        return NGX_ERROR;
    }

    cc->value.len = ngx_sprintf(cc->value.data, "public, max-age=%T", max_age) -
                    cc->value.data;

    return NGX_OK;
}

ngx_int_t set_content_disposition_header(ngx_http_request_t *r,
                                         ngx_str_t *value) {
    auto *h = reinterpret_cast<ngx_table_elt_t *>(
        ngx_list_push(&r->headers_out.headers));
    if (h == nullptr) {
        return NGX_ERROR;
    }

    h->key = CONTENT_DISPOSITION;
    h->lowcase_key = const_cast<u_char *>(CONTENT_DISPOSITION_LOWCASE);
    h->hash = ngx_hash_key(const_cast<u_char *>(CONTENT_DISPOSITION_LOWCASE),
                           sizeof(CONTENT_DISPOSITION_LOWCASE) - 1);

    h->value = *value;

    return NGX_OK;
}

ngx_int_t set_location_header(ngx_http_request_t *r, ngx_str_t *value) {
    auto *h = reinterpret_cast<ngx_table_elt_t *>(
        ngx_list_push(&r->headers_out.headers));
    if (h == nullptr) {
        return NGX_ERROR;
    }

    h->key = LOCATION;
    h->lowcase_key = const_cast<u_char *>(LOCATION_LOWCASE);
    h->hash = ngx_hash_key(const_cast<u_char *>(LOCATION_LOWCASE),
                           sizeof(LOCATION_LOWCASE) - 1);

    h->value = *value;

    return NGX_OK;
}

}  // namespace nginx
}  // namespace weserv
