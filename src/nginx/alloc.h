#pragma once

extern "C" {
#include <ngx_core.h>
}

inline void *operator new(size_t sz, ngx_pool_t *pool) {
    return ngx_pcalloc(pool, sz);
}

inline void *operator new[](size_t sz, ngx_pool_t *pool) {
    return ngx_pcalloc(pool, sz);
}

inline void operator delete(void *ptr, ngx_pool_t *pool) {
    ngx_pfree(pool, ptr);
}

inline void operator delete[](void *ptr, ngx_pool_t *pool) {
    ngx_pfree(pool, ptr);
}

namespace weserv::nginx {

/**
 * Runs an object's destructor. This is useful for destruction of
 * arena-allocated objects.
 */
template <typename T>
void cleanup_handler(void *iv) {
    auto *it = static_cast<T *>(iv);
    it->~T();
}

/**
 * Registers a cleanup handler that runs the destructor of the
 * provided object, such that the destructor will be invoked when the
 * pool is destroyed (via ngx_destroy_pool()).
 *
 * If the pointer is nullptr or if the pool is out of memory, nullptr will
 * be returned; otherwise, the supplied object will be returned.
 */
template <typename T>
T *register_pool_cleanup(ngx_pool_t *pool, T *it) {
    if (!it) {
        return nullptr;
    }
    ngx_pool_cleanup_t *cleanup = ngx_pool_cleanup_add(pool, 0);
    if (!cleanup) {
        return nullptr;
    }
    cleanup->handler = &cleanup_handler<T>;
    cleanup->data = static_cast<void *>(it);
    return it;
}

}  // namespace weserv::nginx
