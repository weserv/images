#pragma once

#include "fixtures.h"

#include <weserv/api_manager.h>
#include <vips/vips8>

using vips::VImage;
using weserv::api::utils::Status;

extern std::shared_ptr<Fixtures> fixtures;
extern std::shared_ptr<weserv::api::ApiManager> api_manager;

extern std::pair<std::string, std::string>
process_buffer(const std::string &buffer, const std::string &query = "");

extern std::pair<std::string, std::string>
process_file(const std::string &file, const std::string &query = "");

extern Status check_buffer_status(const std::string &buffer,
                                  const std::string &query = "");

extern Status check_file_status(const std::string &file,
                                const std::string &query = "");

extern VImage buffer_to_image(const std::string &buffer);