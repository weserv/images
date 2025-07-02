#pragma once

#include "io/source.h"
#include "io/target.h"

#include <weserv/api_manager.h>

namespace weserv::api {

/**
 * Implements ApiManager interface.
 */
class ApiManagerImpl : public ApiManager {
 public:
    explicit ApiManagerImpl(std::unique_ptr<ApiEnvInterface> env);

    ~ApiManagerImpl() override;

    utils::Status process(const std::string &query,
                          const std::unique_ptr<io::SourceInterface> &source,
                          const std::unique_ptr<io::TargetInterface> &target,
                          const Config &config) override;

    utils::Status process_file(const std::string &query,
                               const std::string &in_file,
                               const std::string &out_file,
                               const Config &config) override;

    utils::Status process_file(const std::string &query,
                               const std::string &in_file,
                               std::string *out_buf,
                               const Config &config) override;

    utils::Status process_buffer(const std::string &query,
                                 const std::string &in_buf,
                                 std::string *out_buf,
                                 const Config &config) override;

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
     * Internal processor.
     * @param query Query string.
     * @param source Source to read from.
     * @param target target to write to.
     * @param config API configuration.
     * @return A Status object to represent an error or an OK state.
     */
    utils::Status process(const std::string &query, const io::Source &source,
                          const io::Target &target, const Config &config);

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

}  // namespace weserv::api
