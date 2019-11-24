#pragma once

#include <memory>
#include <string>

#include <weserv/env_interface.h>
#include <weserv/utils/status.h>

namespace weserv {
namespace api {

/**
 * An API Manager interface.
 */
class ApiManager {
 public:
    virtual ~ApiManager() = default;

    /**
     * Process the buffer.
     * @param query Query string.
     * @param in_buf Input buffer.
     * @param out_buf Output buffer.
     * @param out_ext Output extension.
     * @return A Status object to represent an error or an OK state.
     */
    virtual utils::Status process(const std::string &query,
                                  const std::string &in_buf,
                                  std::string *out_buf,
                                  std::string *out_ext) = 0;

 protected:
    ApiManager() = default;
};

class ApiManagerFactory {
 public:
    ApiManagerFactory() = default;

    virtual ~ApiManagerFactory() = default;

    /**
     * Create an ApiManager object.
     * @param env A smart pointer to the environment.
     * @return A smart pointer to the ApiManager object.
     */
    std::shared_ptr<ApiManager>
    create_api_manager(std::unique_ptr<ApiEnvInterface> env);
};

}  // namespace api
}  // namespace weserv
