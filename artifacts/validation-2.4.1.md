# Validation 2.4.1 - 2026-10-30

- 21 Chrome checks against isolated WordPress REST previews: section 1 mode/autoplay, no duplicate selector, no-JavaScript gallery, dot navigation and selected state, CTA fallback/override, new-tab attributes, footer geometry at 600px and 360px, keyboard wrap/focus, single mode, description fallback/visibility, mode switch/undo, and no script errors.
- 49 existing product checks and 19 shortcode option checks passed. The product navigation assertion now expects dots instead of the removed previous arrow.
- 5 existing server preview checks passed.
- PHP and JavaScript syntax checks and git diff whitespace check passed.
- Desktop/mobile screenshots generated and inspected with local fixture artwork.
- Installable 2.4.1 ZIP verified byte-for-byte against every source file and SHA256 generated.

Local isolated WordPress/database and Chrome only. No deployment or GitHub Release performed. Target-site theme compatibility remains to be checked after installation.
