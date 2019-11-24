#include "cli_environment.h"

#include <weserv/api_manager.h>

#include <fstream>
#include <sstream>

using weserv::api::utils::Status;

std::shared_ptr<weserv::api::ApiManager> api_manager;

Status process_file(const std::string &file, const std::string &query,
                    std::string *out_buf, std::string *out_ext) {
    std::ifstream t(file);
    std::ostringstream buffer;
    buffer << t.rdbuf();

    const std::string buf = buffer.str();

    return api_manager->process(query, buf, out_buf, out_ext);
}

inline std::string get_extension(const std::string &base_filename) {
    const size_t idx = base_filename.find_last_of('.');
    return idx != std::string::npos ? base_filename.substr(idx + 1)
                                    : base_filename;
}

auto main(int argc, const char *argv[]) -> int {
    if (argc < 3) {
        std::cout << argv[0] << " image.jpg image2.jpg [ARG1] [ARG2] [...]"
                  << std::endl;
        return 1;
    }

    weserv::api::ApiManagerFactory weserv_factory;
    api_manager = weserv_factory.create_api_manager(
        std::unique_ptr<weserv::api::ApiEnvInterface>(new CliEnvironment()));

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
        query += "output=" + get_extension(argv[2]);
    }

    std::cout << "Processing image \"" << argv[1]
              << "\" with query arguments \"" << query << "\"" << std::endl;

    std::string out_buf;
    std::string out_ext;
    Status status = process_file(argv[1], query, &out_buf, &out_ext);

    if (!status.ok()) {
        std::cerr << "ERROR: " << status.message() << " (" << status.code()
                  << ")" << std::endl;
        return 1;
    }

    std::ofstream out(argv[2]);
    out << out_buf;
    out.close();
}
