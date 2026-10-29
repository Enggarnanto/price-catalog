# Snapshot schema 4

Schema 4 adds `show_description` (boolean) to table and catalog snapshots. `show_title` remains independent. All other schema 2/3 fields and limits apply, including optional table presentation.

Snapshots without `show_description` keep their original schema and display descriptions as before. The editor defaults this control to true and saves schema 4 when edited. Existing snapshots remain immutable and render/restore normally.

Shortcode attributes `show_title` and `show_description` optionally override visibility for one article. Accepted values are 1/true/yes and 0/false/no; omitted or unrecognized values use the resource setting. Overrides only change the rendered copy, not a draft or snapshot.

The optional `nama` attribute is a human-readable package label. Resource resolution and usage indexing continue to use the stable `id`; renaming packages cannot break existing embeds. ID-only shortcodes remain supported.

Do not downgrade to a plugin before 2.0.9 after saving schema 4 snapshots.
