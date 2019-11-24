#include <catch2/catch.hpp>

#include "../base.h"
#include <weserv/utils/status.h>

using Catch::Matchers::Contains;

TEST_CASE("status", "[status]") {
    SECTION("ok returns true when appropriate") {
        CHECK(Status::OK.ok());
        CHECK(Status(0, "OK").ok());
        CHECK(Status(200, "OK").ok());

        CHECK(!Status(/*NGX_ERROR*/ -1, "Out of memory").ok());
        CHECK(!Status(400, "Unable to parse URI").ok());
        CHECK(!Status(404, "Not Found").ok());
    }

    SECTION("http code mapping") {
        CHECK(Status::OK.http_code() == 200);
        CHECK(Status(200, "OK").http_code() == 200);

        // Nginx codes
        CHECK(Status(/*NGX_ERROR*/ -1, "").http_code() == 500);
        CHECK(Status(/*NGX_AGAIN*/ -2, "").http_code() == 100);
        CHECK(Status(/*NGX_BUSY*/ -3, "").http_code() == 429);
        CHECK(Status(/*NGX_DONE*/ -4, "").http_code() == 202);
        CHECK(Status(/*NGX_DECLINED*/ -5, "").http_code() == 404);
        CHECK(Status(/*NGX_ABORT*/ -6, "").http_code() == 400);
        CHECK(Status(/*UNKNOWN*/ -7, "").http_code() == 500);

        // Canonical codes.
        CHECK(Status(Status::Code::Ok, "", Status::ErrorCause::Application)
                  .http_code() == 200);
        CHECK(Status(Status::Code::InvalidImage, "",
                     Status::ErrorCause::Application)
                  .http_code() == 404);
        CHECK(Status(Status::Code::ImageNotReadable, "",
                     Status::ErrorCause::Application)
                  .http_code() == 404);
        CHECK(Status(Status::Code::ImageTooLarge, "",
                     Status::ErrorCause::Application)
                  .http_code() == 404);
        CHECK(Status(Status::Code::InvalidUri, "",
                     Status::ErrorCause::Application)
                  .http_code() == 400);
        CHECK(Status(Status::Code::LibvipsError, "",
                     Status::ErrorCause::Application)
                  .http_code() == 400);
        CHECK(Status(Status::Code::Unknown, "", Status::ErrorCause::Application)
                  .http_code() == 500);
    }

    SECTION("to JSON includes details") {
        auto json = Status::OK.to_json();

        CHECK_THAT(json, Contains(R"("status":"success")"));
        CHECK_THAT(json, Contains(R"("code":200)"));
        CHECK_THAT(json, Contains(R"("message":"OK")"));

        json = Status(500, "").to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":500)"));
        CHECK_THAT(json, Contains(R"("message":"Something's wrong!)"));

        json = Status(/*NGX_ERROR*/ -1, "Out of memory").to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":500)"));
        CHECK_THAT(json, Contains(R"("message":"NGINX returned error: -1)"));
        CHECK_THAT(json, Contains("(message: Out of memory)"));

        json = Status(408, "", Status::ErrorCause::Upstream).to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":404)"));
        CHECK_THAT(json,
                   Contains(R"("message":"The requested URL timed out.")"));

        json = Status(502, "", Status::ErrorCause::Upstream).to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":404)"));
        CHECK_THAT(json, Contains("The hostname of the origin is unresolvable "
                                  "(DNS) or blocked by policy."));

        json = Status(310, "Will not follow a redirection to itself",
                      Status::ErrorCause::Upstream)
                   .to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":404)"));
        CHECK_THAT(
            json,
            Contains(R"("message":"Will not follow a redirection to itself")"));

        json = Status(404, "", Status::ErrorCause::Upstream).to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":404)"));
        CHECK_THAT(
            json,
            Contains(R"("message":"The requested URL returned error: 404")"));

        json = Status(Status::Code::ImageNotReadable,
                      "Image not readable. Is it a valid image?",
                      Status::ErrorCause::Application)
                   .to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":404)"));
        CHECK_THAT(
            json,
            Contains(
                R"("message":"Image not readable. Is it a valid image?")"));

        json = Status(Status::Code::ImageNotReadable, "",
                      Status::ErrorCause::Application)
                   .to_json();

        CHECK_THAT(json, Contains(R"("status":"error")"));
        CHECK_THAT(json, Contains(R"("code":404)"));
        CHECK_THAT(json, Contains(R"("message":"Error code: 3")"));
    }
}
