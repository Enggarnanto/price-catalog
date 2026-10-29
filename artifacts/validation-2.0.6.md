# Validation 2.0.6

- Reproduced the v2.0.5 logo overflow with WordPress core admin-menu.css.
- Seven browser checks: previous overflow, safe intrinsic size without plugin CSS, scoped sizing, unoccluded dashboard control, hover, folded and mobile menus.
- Two PHP hook checks: menu URL uses fixed-size wrapper and Dashboard receives menu CSS only.
- PHP syntax check passed. Original PNG bytes are unchanged and are embedded verbatim into a 20x20 SVG wrapper.
- ZIP root, version, CRC and every source file verified.

No data, API or persistence changes. Production WordPress was not modified.
