#include "base.h"

#define CATCH_CONFIG_RUNNER

#include <catch2/catch.hpp>

#include "test_environment.h"

std::shared_ptr<Fixtures> fixtures;
std::shared_ptr<weserv::api::ApiManager> api_manager;

std::pair<std::string, std::string> process_buffer(const std::string &buffer,
                                                   const std::string &query) {
    std::string out_buf;
    std::string out_ext;
    auto status = api_manager->process(query, buffer, &out_buf, &out_ext);
    if (status == Status::OK) {
        return std::make_pair(out_buf, out_ext);
    }

    throw std::runtime_error(status.message());
}

std::pair<std::string, std::string> process_file(const std::string &file,
                                                 const std::string &query) {
    std::ifstream t(file);
    std::ostringstream buffer;
    buffer << t.rdbuf();

    const std::string buf = buffer.str();

    return process_buffer(buf, query);
}

Status check_buffer_status(const std::string &buffer,
                           const std::string &query) {
    return api_manager->process(query, buffer, nullptr, nullptr);
}

Status check_file_status(const std::string &file, const std::string &query) {
    std::ifstream t(file);
    std::ostringstream buffer;
    buffer << t.rdbuf();

    const std::string buf = buffer.str();

    return check_buffer_status(buf, query);
}

VImage buffer_to_image(const std::string &buffer) {
    return VImage::new_from_buffer(
        buffer, "", VImage::option()->set("access", VIPS_ACCESS_SEQUENTIAL));
}

int main(const int argc, const char *argv[]) {
    Catch::Session session;

    std::string fixtures_dir;

    auto cli =
        session.cli() |
        Catch::clara::Opt(fixtures_dir,
                          "fixtures directory")["-F"]["--fixtures-directory"](
            "change fixtures directory");

    session.cli(cli);

    int return_code = session.applyCommandLine(argc, argv);
    if (return_code != 0) {
        return return_code;
    }

    if (fixtures_dir.empty()) {
        fixtures_dir = "./test/api/fixtures";
    }

    fixtures = std::make_shared<Fixtures>(fixtures_dir);

    weserv::api::ApiManagerFactory weserv_factory;
    api_manager = weserv_factory.create_api_manager(
        std::unique_ptr<weserv::api::ApiEnvInterface>(new TestEnvironment()));

    return session.run();
}
