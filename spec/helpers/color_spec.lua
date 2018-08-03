local weserv_color = require "weserv.helpers.color"

describe("color helper", function()
    describe("test parse", function()
        it("three digit", function()
            local color = weserv_color.new("ABC")

            assert.are.same({ 170, 187, 204, 255 }, color:to_rgba())
        end)

        it("three digit with hash", function()
            local color = weserv_color.new("#ABC")

            assert.are.same({ 170, 187, 204, 255 }, color:to_rgba())
        end)

        it("four digit", function()
            local color = weserv_color.new("0ABC")

            assert.are.same({ 170, 187, 204, 0 }, color:to_rgba())
        end)

        it("four digit with hash", function()
            local color = weserv_color.new("#0ABC")

            assert.are.same({ 170, 187, 204, 0 }, color:to_rgba())
        end)

        it("six digit", function()
            local color = weserv_color.new("11FF33")

            assert.are.same({ 17, 255, 51, 255 }, color:to_rgba())
        end)

        it("six digit with hash", function()
            local color = weserv_color.new("#11FF33")

            assert.are.same({ 17, 255, 51, 255 }, color:to_rgba())
        end)

        it("eight digit", function()
            local color = weserv_color.new("0011FF33")

            assert.are.same({ 17, 255, 51, 0 }, color:to_rgba())
        end)

        it("eight digit with hash", function()
            local color = weserv_color.new("#0011FF33")

            assert.are.same({ 17, 255, 51, 0 }, color:to_rgba())
        end)

        it("named", function()
            local color = weserv_color.new("black")

            assert.are.same({ 0, 0, 0, 255 }, color:to_rgba())
        end)

        it("all none hex", function()
            local color = weserv_color.new("ZXCZXCMM")

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)

        it("one none hex", function()
            local color = weserv_color.new("0123456X")

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)

        it("two digit", function()
            local color = weserv_color.new("01")

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)

        it("five digit", function()
            local color = weserv_color.new("01234")

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)

        it("nine digit", function()
            local color = weserv_color.new("012345678")

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)

        it("nill", function()
            local color = weserv_color.new(nil)

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)

        it("unknown", function()
            local color = weserv_color.new("unknown")

            assert.are.same({ 0, 0, 0, 0 }, color:to_rgba())
        end)
    end)
end)