#include "cli_environment.h"

#include <weserv/api_manager.h>

using weserv::api::Config;
using weserv::api::utils::Status;

std::shared_ptr<weserv::api::ApiManager> api_manager;

inline std::string get_extension(const std::string &base_filename) {
    const size_t idx = base_filename.find_last_of('.');
    return idx != std::string::npos ? base_filename.substr(idx + 1)
                                    : base_filename;
}

int main(int argc, const char *argv[]) {
    if (argc < 3) {
        std::cout << argv[0] << " image.jpg image2.jpg [ARG1] [ARG2] [...]"
                  << std::endl;
        return 1;
    }

    weserv::api::ApiManagerFactory weserv_factory;
    api_manager =
        weserv_factory.create_api_manager(std::make_unique<CliEnvironment>());

    std::string query;

    if (argc > 3) {
        for (int i = 3; i < argc; i++) {
            query += argv[i];

            if (i != argc - 1) {
                query += "&";
            }
        }

        if (query.find("output=") == std::string::npos) {
            query += "&output=" + get_extension(argv[2]);
        }
    } else {
        query = "output=" + get_extension(argv[2]);
    }

    std::cout << "Processing image \"" << argv[1]
              << "\" with query arguments \"" << query << "\"" << std::endl;

    auto config = Config();
    Status status = api_manager->process_file(query, argv[1], argv[2], config);

    if (!status.ok()) {
        std::cerr << "ERROR: " << status.message() << " (" << status.code()
                  << ")" << std::endl;
        return 1;
    }

    return 0;
}
