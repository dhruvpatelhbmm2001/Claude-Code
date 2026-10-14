# vzovalves.com – website edits (14 Oct 2026)

Edits were made directly on the live WordPress site (Manufactory theme + Elementor + WooCommerce).
Custom code runs from `wp-content/novamira-sandbox/vzo-customizations.php` (copy in `wordpress/`).

## Backup / rollback
A full backup was taken before any change was made:
`wp-content/vzo-backup-9aFmR5Z0z9Z2-20261014-091842/` (web access denied via .htaccess). It contains:
- `posts.json`, `postmeta.json`, `posts2.json`: pages, Elementor layouts (header/footer), menus, products, forms, popups, and all their meta (including `_elementor_data`)
- `options.json`: theme mods, WooCommerce, Elementor and widget options
- `terms.json`: categories and term relationships

## Done
| Request | What changed |
|---|---|
| Nav bar fixed | Header rows (desktop + mobile) in layouts 92, 18530 and 31794 now stay pinned with a solid dark background on scroll |
| Remove cart from all pages | Cart icon removed from all 3 headers; `/cart/` and `/checkout/` redirect to the new `/enquiry/` page; prices and buying are turned off |
| Shop → Our Products | Shop page title, footer menu, breadcrumbs, side panel link |
| Brighten Hardware & physical testing photo | Denoised, colour-corrected (yellow cast removed) and brightened (avg brightness 131 → 177) |
| Map click → Bajaj Engineering Works | Home map now pins "Bajaj Engineering Works, M-21-23, Industrial Area, Jalandhar 144008"; clicking any map, the footer "View on Google Map" link, or the Contact page address opens Google Maps |
| Pincode 144008 | Footers, Home map, Contact page (duplicate "Industrial Area" removed) |
| Processes page slow | 18 PNG backgrounds (14.2 MB) replaced with optimized JPEGs (1.1 MB) |
| Welcome paragraphs same colour | Both paragraphs now use the same style (merged into one text block) |
| Remove "!" from "Welcome To" / grammar in para 2 | Now "Welcome to"; both paragraphs copy-edited |
| Remove likes & views | Turned off in theme meta settings and hidden site-wide |
| Product on top | "Our Products Range" section moved directly below the welcome section on Home |
| Better product display (boxed) | Product cards are now bordered boxes with equal-height images, hover lift, 3 per row on desktop |
| Contact us OR enquiries | "Contact us" button → "Contact Us"; footer "Enquire Us Now" (it is a newsletter form) → "Subscribe for Updates"; side panel placeholder text replaced |
| Material filter options (All, Bronze, Cast Iron …) | "By Material" and "Used In" filter pills (with "All") on the Our Products page and every category page |
| Pop-up box wrong info (right side) | Mobile menu / side panel showed demo text "info@website.com", "Go to Shop" and "Send Brief". Now shows the real email, phone, Our Products and Contact Us |
| Created with ❤️ by HB Multimedia | Reduced to 11px, muted |
| Remove photo zoom + lower quality | WooCommerce zoom/lightbox and the Elementor image lightbox are off; product photos no longer link to the full-size file; srcset capped at 1024px; JPEG quality 72 for new uploads |
| Spelling mistakes | Guage → Gauge (3 products), Seprator → Separator (category + product; old URL redirects), Stright → Straight (2 products), Steem → Steam (Our Clients) |
| Cement → Thermal Power Plants | Home "Industries We Serve" |
| Explore product range → popular products | Home category grid now shows Globe, Gate, Check, Ball and Safety Valves, Strainers, Steam Traps, Butterfly Valves and Water Level Gauges |
| Brochure and catalogue | Home buttons: "Download Catalogue" + "Download Brochure" (open in a new tab) |
| Add "Air" under Used in | New "Air" category in the menu. Ball valves and safety valves (19 products) are assigned to it, so please review that list |
| Material of construction duplicates | Repeated Cast Iron, Stainless Steel and Cast Steel menu items removed |
| Industries we serve → related products; remove "How we work" | Each industry icon links to its product category; "How we work" removed |
| Customers add products to a cart → enquiry to inbox | "Add to Enquiry" on every product (list + product page with quantity). A floating "Enquiry List" badge leads to `/enquiry/`, where the customer can set size/qty and submit their details. The enquiry is emailed to sales@vzovalves.com (Reply-To = customer) and also saved in WP Admin → Enquiries |
| Proper product info (partial) | Product pages now show a specification table: Product Code, Type, Material, End Connection, Application, Certification, Sizes & Dimensions (on request + catalogue link). The demo "Reviews" tab is removed |

### Also fixed (found during the work)
- **All Contact Form 7 forms sent to a theme-demo address (`test@fwe.com`)**, so website enquiries never reached you. All 9 forms now send to sales@vzovalves.com from `wordpress@vzovalves.com`, with "Manufactory" branding removed from subjects/bodies. A test email was sent to sales@vzovalves.com.
- The Home map marker pointed to New York (demo coordinates).

## Needs content from you
- Certifications (certificate images/scans)
- Credentials section (Zoloto-style), plus the Timeline (years + milestones)
- Global Presence: the list of export countries
- Dimensions per product: tables (size, A/B/C dims, weight)
- Fuller product descriptions (pressure rating, temperature, standards)
- Installation instructions + O&M manuals (PDFs)
- VZO exhibition details (name, dates, stall, photos)
- Theme video file / link
- "Our process – change the number": steps are numbered 01–18 in sequence. Tell me what number should change
