#!/usr/bin/env python3
"""
Faithful Python port of BD_Phone_Guard_Phone::clean() and ::parse() from
includes/class-bdpg-phone.php, used to verify the algorithm when PHP is not
available on this machine. The PHP suite in run-tests.php remains the
authoritative test — run `php dev/run-tests.php` before every release.
"""

import re

INVISIBLE = re.compile(r"[\u200B-\u200F\u202A-\u202E\u00AD\uFEFF]")
BN_DIGITS = {0x9E6 + i: str(i) for i in range(10)}  # translate() keys are ordinals: ০১২৩৪৫৬৭৮ -> 0-9
NON_DIGIT = re.compile(r"[^0-9]")
REPEATED = re.compile(r"^(\d)\1{7}$")

PREFIXES = {"013", "014", "015", "016", "017", "018", "019"}


def clean(raw):
    value = INVISIBLE.sub("", str(raw))
    value = value.translate(BN_DIGITS)
    return NON_DIGIT.sub("", value)


def parse(raw, reject_repeated=True):
    digits = clean(raw)

    if not digits:
        return {"error": "empty"}

    if digits.startswith("00"):
        digits = digits[2:]

    if digits.startswith("880"):
        digits = digits[3:]
        if digits and not digits.startswith("0"):
            digits = "0" + digits

    if len(digits) == 10 and digits.startswith("1"):
        digits = "0" + digits

    if len(digits) != 11:
        return {"error": "length"}

    prefix = digits[:3]

    if prefix not in PREFIXES:
        return {"error": "prefix"}

    if reject_repeated and REPEATED.match(digits[3:]):
        return {"error": "junk"}

    return {"digits": digits, "prefix": prefix, "e164": "+880" + digits[1:]}


VALID = [
    "01712345678",
    "+8801712345678",
    "8801712345678",
    "008801712345678",
    "(+88) 01712 345678",
    "০১৭১২৩৪৫৬৭৮",
    "৮৮০১৭১২৩৪৫৬৭৮",
    "০1712345678",
    "01712-345678",
    "017 12 34 56 78",
    "+880 1712-345678",
    "017​12345678",  # zero-width space in the middle
    "1712345678",
    "tel:01712345678",
]

INVALID = [
    ("", "empty"),
    ("   ", "empty"),
    ("abc", "empty"),
    ("0171234567", "length"),
    ("017123456789", "length"),
    ("12345", "length"),
    ("01111111111", "prefix"),
    ("01234567890", "prefix"),
    ("02012345678", "prefix"),
    ("01700000000", "junk"),
    ("01877777777", "junk"),
    ("01555555555", "junk"),
]


def main():
    failures = 0
    count = 0

    for raw in VALID:
        count += 1
        result = parse(raw)
        if "error" in result:
            failures += 1
            print(f"FAIL  {raw!r}: should be valid, got {result['error']}")
        elif result["digits"] != "01712345678" or result["e164"] != "+8801712345678":
            failures += 1
            print(f"FAIL  {raw!r}: wrong normalization {result}")

    for raw, code in INVALID:
        count += 1
        result = parse(raw)
        if result.get("error") != code:
            failures += 1
            print(f"FAIL  {raw!r}: expected {code}, got {result}")

    count += 1
    if parse("01712345678", reject_repeated=False).get("error") is not None:
        failures += 1
        print("FAIL  junk check could not be disabled")

    count += 1
    e164 = parse("+8801712345678")
    if e164["digits"][1:] != "1712345678" or len(e164["e164"]) != 14:
        failures += 1
        print(f"FAIL  e164 shape wrong: {e164}")

    print(f"\n{count} checks, {failures} failures")
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
