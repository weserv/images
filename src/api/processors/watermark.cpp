#include "watermark.h"
#include "../utils/utility.h"

namespace weserv::api::processors {

    void Watermark::init_cache_limits() {
        // CRITICAL: Limit libvips operation cache BEFORE any VImage ops
        vips_cache_set_max(50);
        vips_cache_set_max_mem(25 * 1024 * 1024);
        vips_cache_set_max_files(20);
    }

    VImage Watermark::process (const VImage &image) const {
        int width = image.width();
        int height = image.height();

        // Should we process the image?
        if (! query_->exists("wno") || (width <= 150 && height <= 225)) {
            return image;
        }

        std::string wno = std::to_string(query_->get<int>("wno"));
        std::string wsize = width <= 700 ? (width >= 400 ? "_medium" : "_small") : "";
        std::string filename = "/var/www/imagesweserv/watermark/" + wno + wsize + ".png";

        auto watermark_image = VImage::new_from_file(
            filename.c_str(),
            VImage::option()
                ->set("access", VIPS_ACCESS_SEQUENTIAL)
                ->set("fail", true)
                ->set("noload", false)  // Ensure full decode
        );

        if (watermark_image.is_null()) {
            return image;
        }

        // Calculate the position to place the watermark so that it is centered
        int left = (width - watermark_image.width()) / 2;
        int top = (height - watermark_image.height()) / 2;

        // Alpha composite src over dst at the calculated position
        return image.composite2(
            watermark_image,
            VIPS_BLEND_MODE_OVER,
            VImage::option()
                ->set("x", left)
                ->set("y", top)
            );
    }

} // namespace weserv::api::processors
