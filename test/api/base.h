#pragma once

#include "fixtures.h"

#include <vips/vips8>
#include <weserv/api_manager.h>
#include <weserv/enums.h>

using vips::VImage;
using weserv::api::Config;
using weserv::api::enums::Output;
using weserv::api::io::SourceInterface;
using weserv::api::io::TargetInterface;
using weserv::api::utils::Status;

extern std::shared_ptr<Fixtures> fixtures;
extern std::shared_ptr<weserv::api::ApiManager> api_manager;

extern bool pre_8_12;

extern Status process(std::unique_ptr<SourceInterface> source,
                      std::unique_ptr<TargetInterface> target,
                      const std::string &query = "",
                      const Config &config = Config());

template <typename T>
extern T process_buffer(const std::string &buffer,
                        const std::string &query = "",
                        const Config &config = Config());

extern Status process_buffer(const std::string &buffer,
                             std::string *out_buf = nullptr,
                             const std::string &query = "",
                             const Config &config = Config());

template <typename T>
extern T process_file(const std::string &file, const std::string &query = "",
                      const Config &config = Config());

template <typename T>
extern T process_file(const std::string &in_file, std::string *out_file,
                      const std::string &query = "",
                      const Config &config = Config());

extern Status process_file(const std::string &in_file,
                           const std::string &out_file,
                           const std::string &query = "",
                           const Config &config = Config());

extern Status process_file(const std::string &file,
                           std::string *out_buf = nullptr,
                           const std::string &query = "",
                           const Config &config = Config());
