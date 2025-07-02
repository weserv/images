#include "base.h"

#include <catch2/catch_session.hpp>

#include "test_environment.h"

std::shared_ptr<Fixtures> fixtures;
std::shared_ptr<weserv::api::ApiManager> api_manager;

VImage buffer_to_image(const std::string &buf) {
    const char *operation_name =
        vips_foreign_find_load_buffer(buf.data(), buf.size());

    if (operation_name == nullptr) {
        throw std::runtime_error("invalid or unsupported image format");
    }

    VImage out;

    // We must take a copy of the data.
    VipsBlob *blob = vips_blob_copy(buf.data(), buf.size());
    vips::VOption *options = VImage::option()
                                 ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                 ->set("buffer", blob)
                                 ->set("out", &out);
    vips_area_unref(reinterpret_cast<VipsArea *>(blob));

    VImage::call(operation_name, options);

    return out;
}

Status process(const std::unique_ptr<SourceInterface> &source,
               const std::unique_ptr<TargetInterface> &target,
               const std::string &query, const Config &config) {
    return api_manager->process(query, source, target, config);
}

template <>
std::string process_buffer<std::string>(const std::string &buffer,
                                        const std::string &query,
                                        const Config &config) {
    std::string out_buf;
    auto status = api_manager->process_buffer(query, buffer, &out_buf, config);
    if (status.ok()) {
        return out_buf;
    }

    throw std::runtime_error(status.message());
}

template <>
VImage process_buffer<VImage>(const std::string &buffer,
                              const std::string &query, const Config &config) {
    return buffer_to_image(process_buffer<std::string>(buffer, query, config));
}

Status process_buffer(const std::string &buffer, std::string *out_buf,
                      const std::string &query, const Config &config) {
    return api_manager->process_buffer(query, buffer, out_buf, config);
}

template <>
std::string process_file<std::string>(const std::string &file,
                                      const std::string &query,
                                      const Config &config) {
    std::string out_buf;
    auto status = api_manager->process_file(query, file, &out_buf, config);

    if (status.ok()) {
        return out_buf;
    }

    throw std::runtime_error(status.message());
}

template <>
VImage process_file<VImage>(const std::string &file, const std::string &query,
                            const Config &config) {
    return buffer_to_image(process_file<std::string>(file, query, config));
}

template <>
VImage process_file<VImage>(const std::string &in_file, std::string *out_file,
                            const std::string &query, const Config &config) {
    char tmpname[] = "/tmp/imageXXXXXX";
    int fd = mkstemp(tmpname);
    if (fd == -1) {
        throw std::runtime_error("mkstemp temporary file failed");
    }

    auto status = api_manager->process_file(query, in_file, tmpname, config);
    if (status.ok()) {
        VImage image = VImage::new_from_file(
            tmpname, VImage::option()->set("access", VIPS_ACCESS_SEQUENTIAL));
        close(fd);

        if (out_file == nullptr) {
            // Caller is not interested in the output file, so just delete it
            std::remove(tmpname);
        } else {
            // The caller is responsible for deleting the file
            out_file->assign(tmpname, sizeof("/tmp/imageXXXXXX") - 1);
        }

        return image;
    }

    close(fd);
    std::remove(tmpname);
    throw std::runtime_error(status.message());
}

Status process_file(const std::string &in_file, const std::string &out_file,
                    const std::string &query, const Config &config) {
    return api_manager->process_file(query, in_file, out_file, config);
}

Status process_file(const std::string &file, std::string *out_buf,
                    const std::string &query, const Config &config) {
    return api_manager->process_file(query, file, out_buf, config);
}

int main(const int argc, const char *argv[]) {
    Catch::Session session;

    std::string fixtures_dir;

    auto cli =
        session.cli() |
        Catch::Clara::Opt(fixtures_dir,
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
    api_manager =
        weserv_factory.create_api_manager(std::make_unique<TestEnvironment>());

    return session.run();
}
