import pathlib
import sys
import itertools

def main() -> int:
    print(sys.argv[0])
    if len(sys.argv) != 2:
        print(f"usage: python3 {sys.argv[0]} <version>", file=sys.stderr)
        return 1

    version = sys.argv[1]
    file_path = pathlib.Path("plugin.php")

    if not file_path.exists():
        print("plugin.php not found", file=sys.stderr)
        return 1

    lines = file_path.read_text().splitlines(keepends=True)
    updated_line = -1

    stripped = map(str.strip, lines)
    indexed_lines = enumerate(stripped)
    indexed_key_values = itertools.filterfalse(
        lambda il: "=>" not in il[1] or not il[1].endswith(","),
        indexed_lines
    )

    for index, line in indexed_key_values:
        key_part, value_part = line.split("=>", 1)
        key_part = key_part.strip()
        value_part = value_part.strip()

        if key_part.strip("'\"").lower() != "version":
            continue

        indent = line[: len(line) - len(line.lstrip())]
        quote = value_part[0] if value_part and value_part[0] in {"'", '"'} else "'"

        lines[index] = f"{indent}{key_part} => {quote}{version}{quote},\n"
        updated_line = index + 1
        break

    if updated_line < 0:
        print("version mapping not found in plugin.php", file=sys.stderr)
        return 1

    file_path.write_text("".join(lines))
    print(f"Updated plugin.php version entry on line {updated_line}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
