# Snapshot schema v3: package presentation

Price tables containing `presentation` declare snapshot schema 3. Schemas 1 and 2 continue to validate and render as before; existing immutable snapshots are not rewritten. Catalogs keep schema 2.

The complete schema 2 table fields remain. `presentation` adds:

- `theme`: `classic` or `pricing`.
- `featured_column`: zero-based integer; `0` means no highlight. The first column contains row labels, so highlighted packages use indices 1–19 within the table's actual column count.
- `columns`: exactly one entry per header; each entry has `subtitle` (plain text, max 150) and `badge` (plain text, max 50).
- `footer_note`: plain text, max 300.
- `footer_title`: plain text, max 500.

The existing `cta` object supplies the footer button label, HTTP/HTTPS URL and new-tab choice. A missing label/URL pair remains invalid. The renderer shows a WhatsApp icon only for known WhatsApp destination hosts. An empty CTA does not generate a placeholder link. A completely empty footer is omitted.

The pricing theme renders vertical tables. Horizontal orientation retains the original transpose renderer and its standard CTA. The package theme uses a preset navy/yellow/green palette; general styling controls continue to govern standard tables.

Column moves carry their subtitle/badge and feature selection with them. Deleting the selected column clears the selection; deleting earlier columns shifts its index. Paste/CSV expansion creates empty annotations; shrinking removes excess annotations and invalid feature selection.

Maximum snapshot size remains 1 MiB. Unknown keys, markup, invalid dimensions, and invalid URL schemes are rejected. Restore retains the source schema and presentation exactly. Do not downgrade below 2.0.7 after saving tables using schema 3.
