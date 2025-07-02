#include "stream.h"

#include "header.h"
#include "util.h"

namespace weserv::nginx {

ngx_str_t application_json = ngx_string("application/json");

// 1 year by default.
// See: https://github.com/weserv/images/issues/186
constexpr time_t MAX_AGE_DEFAULT = 60 * 60 * 24 * 365;

int64_t NgxSource::read(void *data, size_t length) {
    int64_t bytes_read =
        ngx_min(static_cast<int64_t>(length), length_ - read_position_);
    ngx_memcpy(data, data_ + read_position_, bytes_read);
    read_position_ += bytes_read;
    return bytes_read;
}

int64_t NgxSource::seek(int64_t offset, int whence) {
    int64_t new_pos = 0;

    switch (whence) {
        case SEEK_SET:
            new_pos = offset;
            break;
        case SEEK_CUR:
            new_pos = read_position_ + offset;
            break;
        case SEEK_END:
            new_pos = length_ + offset;
            break;
    }

    // Don't allow out of range seeks
    if (new_pos < 0 || new_pos > length_) {
        return -1;
    }

    read_position_ = new_pos;
    return new_pos;
}

void NgxTarget::setup(const std::string &extension) {
    extension_ = extension;
}

int64_t NgxTarget::write(const void *data, size_t length) {
    int64_t padding = 0;

    if (write_position_ != content_length_) {
        int64_t bytes_written = 0;

        for (/* void */; seek_cl_; seek_cl_ = seek_cl_->next) {
            ngx_buf_t *b = seek_cl_->buf;
            size_t size =
                ngx_min(static_cast<size_t>(b->last - b->pos), length);
            ngx_memcpy(b->pos, data, size);
            bytes_written += size;
            length -= size;

            if (length == 0) {
                write_position_ += bytes_written;

                return bytes_written;
            }
        }

        write_position_ += bytes_written;
        padding = write_position_ - content_length_;
    }

    ngx_buf_t *b = ngx_create_temp_buf(r_->pool, length + padding);
    if (b == nullptr) {
        return -1;
    }

    if (padding > 0) {
        ngx_memzero(b->last, padding);
        b->last += padding;
    }

    b->last = ngx_cpymem(b->last, data, length);
    b->last_buf = 1;

    ngx_chain_t *cl = ngx_alloc_chain_link(r_->pool);
    if (cl == nullptr) {
        return -1;
    }

    cl->buf = b;
    cl->next = nullptr;

    *ll_ = cl;
    ll_ = &cl->next;

    content_length_ += length + padding;
    write_position_ += length;

    return length;
}

int64_t NgxTarget::read(void *data, size_t length) {
    int64_t bytes_read = 0;

    for (/* void */; seek_cl_; seek_cl_ = seek_cl_->next) {
        ngx_buf_t *b = seek_cl_->buf;
        size_t size = ngx_min(static_cast<size_t>(b->last - b->pos), length);

        data = ngx_cpymem(data, b->pos, size);
        b->pos += size;
        bytes_read += size;
        length -= size;

        if (length == 0) {
            break;
        }
    }

    write_position_ += bytes_read;
    return bytes_read;
}

int64_t NgxTarget::seek(int64_t offset, int whence) {
    switch (whence) {
        case SEEK_SET:
            seek_cl_ = *first_ll_;
            write_position_ = offset;
            break;
        case SEEK_CUR:
            write_position_ += offset;
            break;
        case SEEK_END:
            seek_cl_ = nullptr;
            write_position_ = content_length_ + offset;
            break;
    }

    int64_t remainder = 0;

    for (/* void */; seek_cl_; seek_cl_ = seek_cl_->next) {
        ngx_buf_t *b = seek_cl_->buf;
        int64_t size = b->end - b->start;
        int64_t to_seek = ngx_min(size, offset);
        offset -= to_seek;

        if (to_seek == size) {
            continue;
        }

        if (offset == 0) {
            remainder = to_seek;
            break;
        }
    }

    // Mark subsequent buffers as unconsumed
    for (ngx_chain_t *cl = seek_cl_; cl; cl = cl->next) {
        cl->buf->pos = cl->buf->start + remainder;
        remainder = 0;
    }

    return write_position_;
}

int NgxTarget::end() {
    ngx_str_t mime_type = extension_to_mime_type(extension_);

    r_->headers_out.status = NGX_HTTP_OK;
    r_->headers_out.content_type = mime_type;
    r_->headers_out.content_type_len = mime_type.len;
    r_->headers_out.content_type_lowcase = nullptr;
    r_->headers_out.content_length_n = content_length_;

    // Only set the Content-Disposition header on images
    if (!is_base64_needed(r_) &&
        !ngx_string_equal(mime_type, application_json) &&
        set_content_disposition_header(r_, extension_) != NGX_OK) {
        return -1;
    }

    // Only set the Link header if there's an upstream context available
    if (upstream_ctx_ != nullptr &&
        set_link_header(r_, upstream_ctx_->canonical) != NGX_OK) {
        return -1;
    }

    time_t max_age = MAX_AGE_DEFAULT;

    ngx_str_t max_age_str;
    if (ngx_http_arg(r_, (u_char *)"maxage", 6, &max_age_str) == NGX_OK) {
        max_age = parse_max_age(max_age_str);
        if (max_age == static_cast<time_t>(NGX_ERROR)) {
            max_age = MAX_AGE_DEFAULT;
        }
    }

    // Only set Cache-Control and Expires headers on non-error responses
    if (set_expires_header(r_, max_age) != NGX_OK) {
        return -1;
    }

    // Mark all output buffers as unconsumed
    for (ngx_chain_t *cl = *first_ll_; cl; cl = cl->next) {
        cl->buf->pos = cl->buf->start;
    }

    *ll_ = nullptr;

    return 0;
}

}  // namespace weserv::nginx
