#pragma once

#include <weserv/api_manager.h>

#include "parsers/query.h"

#include "processors/alignment.h"
#include "processors/background.h"
#include "processors/blur.h"
#include "processors/brightness.h"
#include "processors/buffer.h"
#include "processors/contrast.h"
#include "processors/crop.h"
#include "processors/embed.h"
#include "processors/filter.h"
#include "processors/gamma.h"
#include "processors/mask.h"
#include "processors/orientation.h"
#include "processors/rotation.h"
#include "processors/sharpen.h"
#include "processors/thumbnail.h"
#include "processors/tint.h"
#include "processors/trim.h"

#include <memory>
#include <string>
#include <utility>

#include <vips/vips8>

namespace weserv {
namespace api {

/**
 * Implements ApiManager interface.
 */
class ApiManagerImpl : public ApiManager {
 public:
    explicit ApiManagerImpl(std::unique_ptr<ApiEnvInterface> env);

    ~ApiManagerImpl() override;

    utils::Status process(const std::string &query, const std::string &in_buf,
                          std::string *out_buf, std::string *out_ext) override;

 private:
    /**
     * Clean up libvips' per-request data and threads.
     */
    void clean_up();

    /**
     * Lippincott function to centralize the exception handling logic.
     * @param query The query string for this request, handy for debugging.
     * @return A Status object to represent the error state.
     */
    utils::Status exception_handler(const std::string &query);

    /**
     * Global environment across multiple services
     */
    std::unique_ptr<ApiEnvInterface> env_;

    /**
     * The id of the VIPS log handler, which was returned in
     * g_log_set_handler().
     */
    unsigned int handler_id_ = 0;
};

}  // namespace api
}  // namespace weserv
