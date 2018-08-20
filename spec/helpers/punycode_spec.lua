local punycode = require "weserv.helpers.punycode"

describe("punycode", function()
    describe("test encode", function()
        it("a single basic code point", function()
            assert.equal("Bach-", punycode.encode("Bach"))
        end)
        it("a single non-ASCII character", function()
            assert.equal("tda", punycode.encode("ü"))
        end)
        it("multiple non-ASCII characters", function()
            assert.equal("4can8av2009b", punycode.encode("üëäö♥"))
        end)
        it("mix of ASCII and non-ASCII characters", function()
            assert.equal("bcher-kva", punycode.encode("bücher"))
        end)
        it("long string with both ASCII and non-ASCII characters", function()
            assert.equal("Willst du die Blthe des frhen, die Frchte des spteren Jahres-x9e96lkal",
                punycode.encode("Willst du die Blüthe des frühen, die Früchte des späteren Jahres"))
        end)
        -- https://tools.ietf.org/html/rfc3492#section-7.1
        it("Arabic (Egyptian)", function()
            assert.equal("egbpdaj6bu4bxfgehfvwxn", punycode.encode("ليهمابتكلموشعربي؟"))
        end)
        it("Chinese (simplified)", function()
            assert.equal("ihqwcrb4cv8a8dqg056pqjye", punycode.encode("他们为什么不说中文"))
        end)
        it("Chinese (traditional)", function()
            assert.equal("ihqwctvzc91f659drss3x8bo0yb", punycode.encode("他們爲什麽不說中文"))
        end)
        it("Czech", function()
            assert.equal("Proprostnemluvesky-uyb24dma41a", punycode.encode("Pročprostěnemluvíčesky"))
        end)
        it("Hebrew", function()
            assert.equal("4dbcagdahymbxekheh6e0a7fei0b",
                punycode.encode("למההםפשוטלאמדבריםעברית"))
        end)
        it("Hindi (Devanagari)", function()
            assert.equal("i1baa7eci9glrd9b2ae1bj0hfcgg6iyaf8o0a1dig0cd",
                punycode.encode("यहलोगहिन्दीक्योंनहींबोलसकतेहैं")) -- luacheck: ignore
        end)
        it("Japanese (kanji and hiragana)", function()
            assert.equal("n8jok5ay5dzabd5bym9f0cm5685rrjetr6pdxa",
                punycode.encode("なぜみんな日本語を話してくれないのか"))
        end)
        it("Korean (Hangul syllables)", function()
            assert.equal("989aomsvi5e83db1d2a355cv1e0vak1dwrv93d5xbh15a0dt30a5jpsd879ccm6fea98c",
                punycode.encode("세계의모든사람들이한국어를이해한다면얼마나좋을까"))
        end)
        it("Russian (Cyrillic)", function()
            -- It doesn"t support mixed-case annotation (which is entirely optional as per the RFC).
            -- So, while the RFC sample string encodes to:
            -- `b1abfaaepdrnnbgefbaDotcwatmq2g4l`
            -- Without mixed-case annotation it has to encode to:
            -- `b1abfaaepdrnnbgefbadotcwatmq2g4l`
            assert.equal("b1abfaaepdrnnbgefbadotcwatmq2g4l",
                punycode.encode("почемужеонинеговорятпорусски"))
        end)
        it("Spanish", function()
            assert.equal("PorqunopuedensimplementehablarenEspaol-fmd56a",
                punycode.encode("PorquénopuedensimplementehablarenEspañol"))
        end)
        it("Vietnamese", function()
            assert.equal("TisaohkhngthchnitingVit-kjcr8268qyxafd2f1b9g",
                punycode.encode("TạisaohọkhôngthểchỉnóitiếngViệt"))
        end)
        it("other", function()
            assert.equal("3B-ww4c5e180e575a65lsy2b", punycode.encode("3年B組金八先生"))
            assert.equal("-with-SUPER-MONKEYS-pc58ag80a8qai00g7n9n",
                punycode.encode("安室奈美恵-with-SUPER-MONKEYS"))
            assert.equal("Hello-Another-Way--fc4qua05auwb3674vfr0b",
                punycode.encode("Hello-Another-Way-それぞれの場所"))
            assert.equal("2-u9tlzr9756bt3uc0v", punycode.encode("ひとつ屋根の下2"))
            assert.equal("MajiKoi5-783gue6qz075azm5e", punycode.encode("MajiでKoiする5秒前"))
            assert.equal("de-jg4avhby1noc0d", punycode.encode("パフィーdeルンバ"))
            assert.equal("d9juau41awczczp", punycode.encode("そのスピードで"))
        end)
    end)

    describe("test domain encode", function()
        it("Emoji", function()
            assert.equal("xn--ls8h.la", punycode.domain_encode("💩.la"))
        end)
        it("invalid", function()
            local idn, err = punycode.domain_encode("--example--.org")
            assert.falsy(idn)
            assert.equal("Invalid domain label", err)
        end)
        it("unchanged", function()
            assert.equal("example.org", punycode.domain_encode("example.org"))
            assert.equal("xn--bcher-kva.com", punycode.domain_encode("xn--bcher-kva.com"))
        end)
        it("separators", function()
            -- label separators as defined by the IDNA RFC
            assert.equal("xn--maana-pta.com", punycode.domain_encode("mañana.com"))
            assert.equal("xn--maana-pta.com", punycode.domain_encode("mañana。com"))
            assert.equal("xn--maana-pta.com", punycode.domain_encode("mañana．com"))
            assert.equal("xn--maana-pta.com", punycode.domain_encode("mañana｡com"))
        end)
        it("other", function()
            assert.equal("xn--maana-pta.com", punycode.domain_encode("mañana.com"))
            assert.equal("xn--bcher-kva.com", punycode.domain_encode("bücher.com"))
            assert.equal("xn--caf-dma.com", punycode.domain_encode("café.com"))
            assert.equal("xn----dqo34k.com", punycode.domain_encode("☃-⌘.com"))
            assert.equal("xn----dqo34kn65z.com", punycode.domain_encode("퐀☃-⌘.com"))
            assert.equal("xn--j1ail.xn--p1ai", punycode.domain_encode("кто.рф"))
        end)
    end)
end)