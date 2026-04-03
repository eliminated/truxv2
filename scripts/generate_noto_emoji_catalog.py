from __future__ import annotations

import json
import re
import shutil
from collections import defaultdict
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable


ROOT = Path(__file__).resolve().parents[1]
SOURCE_ROOT = ROOT / ".tmp" / "noto-emoji"
EMOJI_TEST_PATH = ROOT / ".tmp" / "emoji-test-17.0.txt"
OUTPUT_ROOT = ROOT / "public" / "assets" / "emoji" / "noto"
SVG_OUTPUT_ROOT = OUTPUT_ROOT / "svg"
PNG_OUTPUT_ROOT = OUTPUT_ROOT / "png" / "128"
FLAGS_OUTPUT_ROOT = OUTPUT_ROOT / "flags"
FLAGS_SOURCE_ROOT = SOURCE_ROOT / "third_party" / "region-flags" / "png"

SKIN_TONES = {0x1F3FB, 0x1F3FC, 0x1F3FD, 0x1F3FE, 0x1F3FF}
GENDER_SIGNS = {0x2640, 0x2642}
EMOJI_VS = 0xFE0F
ZWJ = 0x200D
BLACK_FLAG = 0x1F3F4
CANCEL_TAG = 0xE007F
ADULT_MAP = {
    0x1F468: 0x1F9D1,
    0x1F469: 0x1F9D1,
}

CATEGORY_META = {
    "Smileys & Emotion": {
        "id": "smileys-emotion",
        "label": "Smileys & Emotion",
        "icon": (0x1F600,),
    },
    "People & Body": {
        "id": "people-body",
        "label": "People & Body",
        "icon": (0x1F44B,),
    },
    "Component": {
        "id": "component",
        "label": "Component",
        "icon": (0x1F3FB,),
    },
    "Animals & Nature": {
        "id": "animals-nature",
        "label": "Animals & Nature",
        "icon": (0x1F436,),
    },
    "Food & Drink": {
        "id": "food-drink",
        "label": "Food & Drink",
        "icon": (0x1F355,),
    },
    "Travel & Places": {
        "id": "travel-places",
        "label": "Travel & Places",
        "icon": (0x1F30D,),
    },
    "Activities": {
        "id": "activities",
        "label": "Activities",
        "icon": (0x26BD,),
    },
    "Objects": {
        "id": "objects",
        "label": "Objects",
        "icon": (0x1F4A1,),
    },
    "Symbols": {
        "id": "symbols",
        "label": "Symbols",
        "icon": (0x2764,),
    },
    "Flags": {
        "id": "flags",
        "label": "Flags",
        "icon": (0x1F3C1,),
    },
}


@dataclass
class EmojiEntry:
    sequence: tuple[int, ...]
    emoji: str
    name: str
    keywords: list[str]
    group: str
    subgroup: str
    asset_key: str
    asset_path: str
    asset_kind: str
    sort_order: int
    category_id: str
    root_name: str
    variants: list["EmojiEntry"]
    parent_sequence: tuple[int, ...] | None = None

    @property
    def codepoints(self) -> list[str]:
        return [f"{codepoint:04X}" for codepoint in self.sequence]

    def to_manifest(self, include_variants: bool = True) -> dict[str, object]:
        payload: dict[str, object] = {
            "emoji": self.emoji,
            "codepoints": self.codepoints,
            "name": self.name,
            "keywords": self.keywords,
            "asset_path": self.asset_path,
        }
        if include_variants:
            payload["variants"] = [
                variant.to_manifest(include_variants=False) for variant in self.variants
            ]
        return payload


def strip_fe0f(sequence: Iterable[int]) -> tuple[int, ...]:
    return tuple(codepoint for codepoint in sequence if codepoint != EMOJI_VS)


def sequence_to_asset_key(sequence: Iterable[int]) -> str:
    stripped = strip_fe0f(sequence)
    return "_".join(f"{codepoint:04x}" for codepoint in stripped)


def sequence_to_emoji(sequence: Iterable[int]) -> str:
    return "".join(chr(codepoint) for codepoint in sequence)


def tokenize_keywords(*values: str) -> list[str]:
    tokens: list[str] = []
    seen: set[str] = set()
    for value in values:
        for token in re.findall(r"[a-z0-9]+", value.lower()):
            if token in seen or len(token) < 2:
                continue
            seen.add(token)
            tokens.append(token)
    return tokens


def parse_aliases(alias_path: Path) -> dict[str, str]:
    aliases: dict[str, str] = {}
    for raw_line in alias_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or ";" not in line:
            continue
        source, target = [part.strip() for part in line.split(";", 1)]
        if source and target:
            aliases[source] = target
    return aliases


def resolve_alias(asset_key: str, aliases: dict[str, str], seen: set[str] | None = None) -> str:
    history = seen or set()
    current = asset_key
    while current in aliases and current not in history:
        history.add(current)
        current = aliases[current]
    return current


def parse_emoji_test(path: Path) -> list[dict[str, object]]:
    group = ""
    subgroup = ""
    items: list[dict[str, object]] = []
    entry_pattern = re.compile(
        r"^([0-9A-F ]+)\s*;\s*(component|fully-qualified)\s*#\s*(.+?)\s+E[0-9.]+\s+(.+)$"
    )

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line:
            continue
        if line.startswith("# group:"):
            group = line.split(":", 1)[1].strip()
            continue
        if line.startswith("# subgroup:"):
            subgroup = line.split(":", 1)[1].strip()
            continue

        matched = entry_pattern.match(line)
        if not matched or group == "":
            continue

        codepoints = tuple(int(part, 16) for part in matched.group(1).split())
        items.append(
            {
                "sequence": codepoints,
                "emoji": sequence_to_emoji(codepoints),
                "name": matched.group(4).strip(),
                "group": group,
                "subgroup": subgroup,
            }
        )
    return items


def strip_skin_tones(sequence: Iterable[int]) -> tuple[int, ...]:
    return tuple(codepoint for codepoint in sequence if codepoint not in SKIN_TONES)


def strip_gender_signs(sequence: Iterable[int]) -> tuple[int, ...]:
    source = list(sequence)
    result: list[int] = []
    index = 0
    while index < len(source):
        codepoint = source[index]
        if codepoint in GENDER_SIGNS:
            if result and result[-1] == ZWJ:
                result.pop()
            if index + 1 < len(source) and source[index + 1] == ZWJ:
                index += 1
            index += 1
            continue
        result.append(codepoint)
        index += 1
    while result and result[-1] == ZWJ:
        result.pop()
    return tuple(result)


def replace_gendered_people(sequence: Iterable[int]) -> tuple[int, ...]:
    return tuple(ADULT_MAP.get(codepoint, codepoint) for codepoint in sequence)


def normalize_root_name(name: str) -> str:
    lower_name = name.lower().strip()
    if ":" in lower_name:
        lower_name = lower_name.split(":", 1)[0].strip()
    for prefix in ("man ", "woman ", "person "):
        if lower_name.startswith(prefix):
            return lower_name[len(prefix) :].strip()
    return lower_name


def is_regional_indicator(codepoint: int) -> bool:
    return 0x1F1E6 <= codepoint <= 0x1F1FF


def regional_indicator_to_ascii(codepoint: int) -> str:
    return chr(ord("A") + (codepoint - 0x1F1E6))


def decode_tag_code(codepoint: int) -> str:
    if 0xE0061 <= codepoint <= 0xE007A:
        return chr(ord("a") + (codepoint - 0xE0061))
    if 0xE0030 <= codepoint <= 0xE0039:
        return chr(ord("0") + (codepoint - 0xE0030))
    if codepoint == 0xE002D:
        return "-"
    return ""


def flag_asset_name(sequence: tuple[int, ...]) -> str | None:
    if len(sequence) == 2 and all(is_regional_indicator(codepoint) for codepoint in sequence):
        return "".join(regional_indicator_to_ascii(codepoint) for codepoint in sequence) + ".png"

    stripped = strip_fe0f(sequence)
    if len(stripped) < 3 or stripped[0] != BLACK_FLAG or stripped[-1] != CANCEL_TAG:
        return None

    tag_code = "".join(decode_tag_code(codepoint) for codepoint in stripped[1:-1])
    if tag_code.startswith("gb") and len(tag_code) > 2:
        return f"GB-{tag_code[2:].upper()}.png"
    if tag_code.startswith("us") and len(tag_code) > 2:
        return f"US-{tag_code[2:].upper()}.png"
    if tag_code:
        return tag_code.upper() + ".png"
    return None


def pick_asset_path(
    asset_key: str,
    sequence: tuple[int, ...],
    group: str,
    subgroup: str,
    aliases: dict[str, str],
    svg_files: set[str],
    png_files: set[str],
    flag_png_files: set[str],
) -> tuple[str, str]:
    if group == "Flags" or "flag" in subgroup:
        flag_name = flag_asset_name(sequence)
        if flag_name and flag_name in flag_png_files:
            return "png", f"flags/{flag_name}"

    resolved = resolve_alias(asset_key, aliases)
    if resolved in svg_files:
        return "svg", f"svg/emoji_u{resolved}.svg"
    if resolved in png_files:
        return "png", f"png/128/emoji_u{resolved}.png"
    raise FileNotFoundError(f"No asset found for {asset_key}")


def copy_file(source: Path, destination: Path) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, destination)


def build_entries() -> tuple[list[EmojiEntry], dict[str, tuple[str, str]]]:
    if not SOURCE_ROOT.exists():
        raise FileNotFoundError(f"Missing Noto source checkout: {SOURCE_ROOT}")
    if not EMOJI_TEST_PATH.exists():
        raise FileNotFoundError(f"Missing emoji-test data: {EMOJI_TEST_PATH}")

    aliases = parse_aliases(SOURCE_ROOT / "emoji_aliases.txt")
    svg_files = {path.stem.replace("emoji_u", "", 1) for path in (SOURCE_ROOT / "svg").glob("emoji_u*.svg")}
    png_files = {
        path.stem.replace("emoji_u", "", 1)
        for path in (SOURCE_ROOT / "png" / "128").glob("emoji_u*.png")
    }
    flag_png_files = {path.name for path in FLAGS_SOURCE_ROOT.glob("*.png")}

    raw_items = parse_emoji_test(EMOJI_TEST_PATH)
    entries: list[EmojiEntry] = []

    for sort_order, item in enumerate(raw_items):
        group = str(item["group"])
        if group not in CATEGORY_META:
            continue

        sequence = tuple(item["sequence"])
        asset_key = sequence_to_asset_key(sequence)
        subgroup = str(item["subgroup"])
        asset_kind, asset_path = pick_asset_path(
            asset_key,
            sequence,
            group,
            subgroup,
            aliases,
            svg_files,
            png_files,
            flag_png_files,
        )
        category_id = CATEGORY_META[group]["id"]
        name = str(item["name"])
        root_name = normalize_root_name(name)
        entries.append(
            EmojiEntry(
                sequence=sequence,
                emoji=str(item["emoji"]),
                name=name,
                keywords=tokenize_keywords(name, group, subgroup, root_name),
                group=group,
                subgroup=subgroup,
                asset_key=asset_key,
                asset_path=asset_path,
                asset_kind=asset_kind,
                sort_order=sort_order,
                category_id=category_id,
                root_name=root_name,
                variants=[],
            )
        )

    asset_lookup: dict[str, tuple[str, str]] = {}
    for entry in entries:
        if entry.asset_path.startswith("flags/"):
            source = FLAGS_SOURCE_ROOT / Path(entry.asset_path).name
        elif entry.asset_path.startswith("svg/"):
            source = SOURCE_ROOT / "svg" / f"emoji_u{resolve_alias(entry.asset_key, aliases)}.svg"
        else:
            source = SOURCE_ROOT / "png" / "128" / f"emoji_u{resolve_alias(entry.asset_key, aliases)}.png"
        asset_lookup[entry.asset_path] = (entry.asset_kind, str(source))
    return entries, asset_lookup


def assign_variants(entries: list[EmojiEntry]) -> list[EmojiEntry]:
    entries_by_sequence = {strip_fe0f(entry.sequence): entry for entry in entries}
    root_name_index: dict[str, EmojiEntry] = {}
    for entry in entries:
        candidate = root_name_index.get(entry.root_name)
        if candidate is None or len(entry.sequence) < len(candidate.sequence):
            root_name_index[entry.root_name] = entry

    for entry in entries:
        normalized = strip_fe0f(entry.sequence)
        candidates: list[tuple[int, ...]] = []

        without_skin = strip_fe0f(strip_skin_tones(normalized))
        if without_skin != normalized:
            candidates.append(without_skin)

        without_gender = strip_fe0f(strip_gender_signs(normalized))
        if without_gender != normalized:
            candidates.append(without_gender)
            without_gender_skin = strip_fe0f(strip_skin_tones(without_gender))
            if without_gender_skin != without_gender:
                candidates.append(without_gender_skin)

        personized = strip_fe0f(replace_gendered_people(normalized))
        if personized != normalized:
            candidates.append(personized)
            personized_skin = strip_fe0f(strip_skin_tones(personized))
            if personized_skin != personized:
                candidates.append(personized_skin)
            personized_gender = strip_fe0f(strip_gender_signs(personized))
            if personized_gender != personized:
                candidates.append(personized_gender)

        root_candidate = root_name_index.get(entry.root_name)
        if root_candidate is not None and root_candidate.sequence != entry.sequence:
            candidates.append(strip_fe0f(root_candidate.sequence))

        parent: EmojiEntry | None = None
        for candidate_sequence in candidates:
            if candidate_sequence == normalized:
                continue
            candidate_entry = entries_by_sequence.get(candidate_sequence)
            if candidate_entry is None or candidate_entry is entry:
                continue
            parent = candidate_entry
            break

        if parent is not None:
            entry.parent_sequence = parent.sequence
            parent.variants.append(entry)

    for entry in entries:
        entry.variants.sort(key=lambda item: item.sort_order)

    base_entries = [entry for entry in entries if entry.parent_sequence is None]
    base_entries.sort(key=lambda item: item.sort_order)
    return base_entries


def build_manifest(base_entries: list[EmojiEntry], entries: list[EmojiEntry]) -> dict[str, object]:
    categories: list[dict[str, object]] = []
    grouped: dict[str, list[EmojiEntry]] = defaultdict(list)
    for entry in base_entries:
        grouped[entry.group].append(entry)

    entry_lookup = {entry.asset_key: entry for entry in entries}

    for group_name, meta in CATEGORY_META.items():
        category_entries = grouped.get(group_name, [])
        if not category_entries:
            continue

        icon_sequence = tuple(meta["icon"])
        icon_key = sequence_to_asset_key(icon_sequence)
        icon_entry = entry_lookup.get(icon_key)
        icon_asset_path = icon_entry.asset_path if icon_entry is not None else category_entries[0].asset_path
        categories.append(
            {
                "id": meta["id"],
                "label": meta["label"],
                "icon_emoji": sequence_to_emoji(icon_sequence),
                "icon_asset_path": icon_asset_path,
                "items": [entry.to_manifest() for entry in category_entries],
            }
        )

    return {
        "vendor": "google-noto-emoji",
        "release": "v2.051",
        "unicode_version": "17.0",
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "categories": categories,
        "totals": {
            "base_items": len(base_entries),
            "all_items": len(entries),
        },
    }


def rebuild_output(asset_lookup: dict[str, tuple[str, str]]) -> None:
    if OUTPUT_ROOT.exists():
        shutil.rmtree(OUTPUT_ROOT)
    SVG_OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)
    PNG_OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)
    FLAGS_OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)

    for asset_path, (_, source) in asset_lookup.items():
        destination = OUTPUT_ROOT / asset_path
        copy_file(Path(source), destination)

    copy_file(SOURCE_ROOT / "LICENSE", OUTPUT_ROOT / "LICENSE.txt")
    copy_file(SOURCE_ROOT / "third_party" / "region-flags" / "LICENSE", OUTPUT_ROOT / "FLAGS-LICENSE.txt")


def main() -> None:
    entries, asset_lookup = build_entries()
    base_entries = assign_variants(entries)
    rebuild_output(asset_lookup)
    manifest = build_manifest(base_entries, entries)
    (OUTPUT_ROOT / "catalog.json").write_text(
        json.dumps(manifest, ensure_ascii=False, separators=(",", ":")),
        encoding="utf-8",
    )
    print(
        json.dumps(
            {
                "base_items": len(base_entries),
                "all_items": len(entries),
                "output_root": str(OUTPUT_ROOT),
            }
        )
    )


if __name__ == "__main__":
    main()
