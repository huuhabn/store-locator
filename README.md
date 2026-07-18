# Aseer Store Locator

A store locator plugin built with Leaflet.js (Google Map API Support), a REST API backend, marker clustering, live filtering, and CSV bulk import.

## Features

- Custom post type (`store`) for managing store data from wp-admin
- 11 store fields: name, brand, country, city, address, lat/lng, phone, email, opening hours, details, directions URL
- CSV importer for bulk loading 500+ stores (create + update in one pass)
- Frontend shortcode `[store_locator]` with:
  - Interactive Leaflet.js map, choice of 4 basemap styles (see Settings)
  - Marker clustering, auto-fit bounds, popups, admin-configurable pin color/icon
  - Search box with "use my location" + place autocomplete (OpenStreetMap Nominatim)
  - Collapsible filter panel: checkbox filters for brand, dropdown filters for country/city
  - Nearest-first sorting once the user shares their location
  - Store cards with open/closed status, expandable hours, distance, directions button, details modal
  - Mobile map/list toggle layout
- **Settings page** (Store Locator → Settings): marker color/icon, primary + panel colors, map basemap style, default map center/zoom
- **Listing detail page**: every store gets its own URL (`/store/store-name/`) rendering a full detail page — theme-overridable, see below
- REST API (`/wp-json/aseer-store-locator/v1/stores`, `/filters`) with pagination
- Security: nonces, capability checks, sanitized input, escaped output, prepared queries
- Styling driven entirely by CSS variables so it inherits your theme's look

## Listing Detail Page (Overriding Templates)

Every store is a real WordPress page at its own URL (e.g. `/store/altamonte-mall/`), rendered by `templates/single-store.php` — a normal WordPress Loop template (title, featured image, address, phone/email, hours, a small map, and a Directions button).

**To customize it:** copy the file to your theme (or child theme) at

```
yourtheme/aseer-store-locator/single-store.php
```

and edit that copy. The plugin always checks the theme first (child theme, then parent theme) and only falls back to its own bundled copy if no theme version exists — a plugin update will never overwrite your customized copy. This is the same override pattern used by WooCommerce, Easy Digital Downloads, etc.

The `[store_locator]` widget's own template (`templates/locator.php`) can be overridden the exact same way, at `yourtheme/aseer-store-locator/locator.php`.

> **After updating from a version without this feature:** visit **Settings → Permalinks** in wp-admin and click **Save Changes** once — WordPress only registers the new `/store/...` URLs on save/activation, not automatically on plugin update.

## Installation

1. Zip the `aseer-store-locator` folder (or use the provided `aseer-store-locator.zip`).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip file and click **Install Now**, then **Activate**.
4. A new **Store Locator** menu item will appear in the left admin sidebar.

## Adding Stores

**Manually:**
Go to **Store Locator → Add New Store**, fill in the title (store name) and the Store Details meta box fields (brand, country, city, address, latitude/longitude, phone, email, hours, details, directions URL override), then Publish.

**Via CSV (recommended for 500+ locations):**
1. Go to **Store Locator → Import CSV**.
2. Prepare a CSV with this exact column order:
   ```
   name,store_brand,store_country,address,coordinates,phone,opening_hours,direction_url
   ```
   (`coordinates` is a single `"lat,lng"` cell, and `direction_url` is an optional external directions link — wrap any value containing a comma in quotes. See `stores-example.csv` in this package for a working sample.)
3. Upload the file and click **Import Stores**.
4. Existing stores are matched by exact **Store Name + Brand** and updated; unmatched rows are created as new stores. Invalid rows (missing name, out-of-range lat/lng, or a column count that doesn't match the header — usually an un-quoted comma inside a text field) are skipped and reported in the results summary instead of failing the whole import.

## Using the Shortcode

Add this to any page or post:

```
[store_locator]
```

Optional attributes:

```
[store_locator height="700px" default_zoom="5"]
```

| Attribute | Default | Description |
|---|---|---|
| `height` | `650px` | Height of the map/sidebar area (any valid CSS length) |
| `default_zoom` | *(Settings page value)* | Initial Leaflet zoom level before stores load |
| `default_center` | *(Settings page value)* | Initial map center as `"lat,lng"`, e.g. `"24.7,46.6"` |

## Settings Page

Go to **Store Locator → Settings** to configure, without touching code:

- **Marker Color** / **Custom Marker Icon URL** — the pin color (or a custom PNG/SVG image) used for every store marker on the map
- **Primary / Button Color** — buttons, links, focus outlines
- **Search Panel Background** — the search box + results/filter bar background (pink in the default Victoria's Secret–style theme, but any color)
- **Map Provider** — **Leaflet** (default: free, no API key, uses OpenStreetMap/CARTO tiles) or **Google Maps** (requires a Google Cloud API key). See "Map Provider" below for details.
- **Map Style** — Leaflet only: Standard (OpenStreetMap), Light (Positron), Dark (Dark Matter), or Voyager — all free CARTO/OSM basemaps, no API key needed
- **Default Map Center / Zoom** — where the map starts before any search runs (a shortcode's own `default_zoom`/`default_center` attribute always takes priority over this)

## Map Provider

The plugin defaults to **Leaflet** with free OpenStreetMap/CARTO tiles — no account or API key needed, works out of the box.

To switch to **Google Maps** instead:

1. In [Google Cloud Console](https://console.cloud.google.com/), enable the **Maps JavaScript API** for a project (requires billing to be enabled on the project — Google's free monthly credit covers typical small/medium traffic).
2. Create an API key under **APIs & Services → Credentials**, and restrict it (HTTP referrers) to your site's domain(s). This is what makes it safe for the key to appear in your page's HTML/JS — that's normal for the Maps JavaScript API, not a leak.
3. On **Store Locator → Settings**, set **Map Provider** to *Google Maps* and paste the key into **Google Maps API Key**.

Notes:
- If Google Maps is selected but no key is set, the frontend automatically falls back to Leaflet (with a warning shown on the Settings page) rather than showing a broken map.
- Marker clustering on Google Maps uses the optional `@googlemaps/markerclusterer` library (loaded from a CDN, same approach as Leaflet's cluster plugin); if it fails to load for any reason, markers still render individually rather than the map breaking.
- **Location autocomplete in the search box always uses OpenStreetMap's Nominatim**, regardless of which map provider is active — the two are independent (Nominatim just looks up place names/coordinates; it doesn't render the map itself).
- The "Dark Matter" Map Style option applies an approximate dark theme to Google Maps too (Google doesn't have that exact CARTO basemap, so this is a hand-built equivalent, not a pixel-perfect match). All other Map Style choices are Leaflet-only and are ignored when Google Maps is active.

## Styling / Branding

Colors set on the Settings page are injected automatically. For anything else (radius, font, muted/border colors), override the CSS variables on `.asl-locator` from your theme's stylesheet instead of editing the plugin, e.g.:

```css
.asl-locator {
  --asl-color-text: #1a1a1a;
  --asl-radius: 4px;
  --asl-font: "Poppins", sans-serif;
}
```

## Changing the Map Tile Provider

Pick from the 4 built-in styles on the Settings page. To add a different provider entirely (e.g. Mapbox, Maptiler), add an entry to the `tile_providers()` array in `includes/Frontend/Assets.php` and to the `$options` list in `field_tile_style()` in `includes/Admin/Settings.php`.

## Location Autocomplete

The search box's autocomplete suggestions come from OpenStreetMap's free Nominatim geocoder (same open-data stack as the map tiles — no API key). Nominatim's usage policy caps free public use at roughly 1 request/second per client; the search input is debounced to stay well under that for normal traffic. A site expecting heavy search volume should proxy this through its own server or switch to a commercial geocoder.

## REST API Reference

- `GET /wp-json/aseer-store-locator/v1/stores` — params: `brand`, `country`, `city`, `search`, `page`, `per_page` (max 500)
- `GET /wp-json/aseer-store-locator/v1/filters` — returns distinct brand/country/city values for populating filter dropdowns

Both endpoints are public/read-only (`GET` only) and return published stores only.

## File Structure

```
aseer-store-locator/
├── aseer-store-locator.php        Plugin bootstrap
├── includes/
│   ├── Plugin.php                 Wires everything together
│   ├── PostTypes/StorePostType.php
│   ├── Admin/MetaBoxes.php        Edit-screen fields, admin list columns/filter
│   ├── Admin/Import.php           CSV importer page + handler
│   ├── Admin/Settings.php         Marker/color/map settings page
│   ├── Frontend/Shortcode.php     [store_locator] shortcode
│   ├── Frontend/Templates.php     Theme-override template locator/loader
│   ├── Frontend/TemplateLoader.php  Swaps in single-store.php on store URLs
│   ├── Frontend/OpeningHours.php  Best-effort "open now" + hours-table parsing
│   ├── Frontend/Assets.php        Conditional asset loading + settings → CSS/JS bridge
│   └── Rest/StoreController.php   REST endpoints
├── templates/
│   ├── locator.php                [store_locator] widget markup (overridable)
│   └── single-store.php           Listing detail page (overridable)
├── assets/js/store-locator.js     Map + filters + geolocation logic
├── assets/css/store-locator.css   Themeable styles
├── templates/locator.php          Frontend markup
└── stores-example.csv             Sample import file
```

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Outbound access to `unpkg.com` (Leaflet CDN), `tile.openstreetmap.org`/`basemaps.cartocdn.com` (map tiles), and `nominatim.openstreetmap.org` (search autocomplete) from visitors' browsers

## Notes / Next Steps

- Distance-based sorting and "open now" status are computed client-side from opening-hours free text using a best-effort parser; for exact multi-day schedules consider extending `_asl_opening_hours` to a structured format later.
- The importer currently accepts the fixed column order specified above; a future version could support header-based column mapping for flexibility.

## Recent Fixes (v1.3.0)

- **"Use My Location" now actually zooms in.** Previously the map's re-fit-to-all-stores logic ran right after locating the user and silently undid the zoom; the map now stays centered on the user at zoom 14.
- **Download Example Template.** Store Locator → Import CSV now has a "Download Example Template (CSV)" button that streams a ready-to-edit CSV with the correct header and a few example rows.
- **Button styling is theme-resistant.** All interactive elements (`.asl-btn`, filter/search icon buttons, checkboxes, the mobile list/map toggle, etc.) are now scoped under `.asl-locator` with tag-qualified selectors (`a.asl-btn`, `button.asl-btn`, …) and explicit resets (`text-decoration`, `box-shadow`, `appearance`, etc.), so an active theme's own generic `button`/`a` styles no longer bleed through and override them.
- **Fixed double-encoded text from the REST API.** `get_the_title()` HTML-entity-encodes things like apostrophes and ampersands for direct HTML output; since the frontend JS escapes text itself before inserting it into the page, that pre-encoded text was being escaped a second time and showing up as literal `&#8217;`/`&#038;` instead of the actual characters. The REST API now decodes entities before returning JSON, so exactly one escaping step happens (in the browser).

## v1.4.0 — Google Maps support

- **New Map Provider setting.** Choose Leaflet (default) or Google Maps on the Settings page — see "Map Provider" above. Both the `[store_locator]` widget and the single-store detail page's mini map support either provider, including marker icons/colors, clustering, popups, and the "Use My Location" zoom behavior.
- Assets now only load the scripts for whichever provider is actually configured (e.g. Leaflet's ~150KB never loads when Google Maps is selected, and vice versa).

## v1.5.0

- **Fixed: clicking a store in the list didn't reliably pan/center the map.** Both Leaflet and Google Maps compute the wrong view if you pan while the map container is still hidden (`display:none`) — a classic issue when the map is inside a hidden mobile tab (or, relatedly, a hidden Elementor tab during editing). `focusStore()` now forces a size recalculation right before centering, fixing this for mobile and any other hidden-container scenario.
- **Fixed: `[store_locator]` mostly not working inside Elementor.** Elementor's "Shortcode" widget stores the shortcode text in `_elementor_data` postmeta (JSON), not `$post->post_content` — `has_shortcode()` never found it there, so the plugin's CSS/JS never loaded (the widget rendered, just completely unstyled/non-interactive). Fixed two ways: (1) `should_enqueue()` now also checks `_elementor_data` for a proactive, in-`<head>` load; (2) the existing `wp_footer` safety-net fallback now force-prints its stylesheet (`wp_print_styles()`) since WordPress only auto-prints late-enqueued *scripts*, not styles — the fallback was silently missing CSS even when it correctly loaded the JS.
- **Redesigned the listing detail page** to match a Victoria's Secret–style store page: breadcrumb, a three-column info panel (Store Details / Store Hours / Store Services) with vertical dividers, a pink "Get Directions" CTA button, and the map + content moved below the panel.
  - **New:** a real "Open Now"/"Closed" status and a day-by-day-looking hours table, both computed from the existing free-text `_asl_opening_hours` field (best-effort parsing — see `Frontend/OpeningHours.php`) — no new admin fields needed, works with hours already entered in a "Day: hours" per-line format.
  - **Deliberately not included** (no matching data source, not faked): a separate "Store Includes" list distinct from "Services" (this plugin has one `services` field, shown once), and social media links (no such field exists). Ask if you'd like either added as real fields.

## v1.5.1

- **Fixed: `[store_locator]` still not working in the Elementor editor.** The earlier fix only addressed detecting the shortcode in a page's content; the actual reason it "mostly doesn't work" while editing is that Elementor's Shortcode widget re-renders via an AJAX request straight to `admin-ajax.php` whenever its content changes — that request type never fires `wp_enqueue_scripts` at all (no page/footer to print into), so there's no way to catch it after the fact. Fixed by always loading the plugin's assets on Elementor's preview-iframe page load (detected via its `elementor-preview` query var), so they're already present in the iframe before any later AJAX re-render happens.
- **Added a slim scrollbar** to the store list panel (and, for consistency, the search autocomplete dropdown and the details modal) instead of the browser's default scrollbar — styled via `--asl-color-border`/`--asl-color-muted`, works in both Firefox (`scrollbar-width`/`scrollbar-color`) and Chromium/Safari (`::-webkit-scrollbar`).

## v1.5.28

- **Removed the "Services" field.** The per-store services list has been dropped everywhere — the `_asl_services` meta box field and registration, the REST `services` query param and the `services` values in the `/stores` and `/filters` responses, the "Store Services" column on the single-store detail page (now a two-column Details / Hours panel), and the related CSS. Any previously stored `_asl_services` post meta is simply ignored.
- **CSV import: `services` column replaced with `direction_url`.** The importer now maps the last column to the store's external directions URL (`_asl_directions_url`) instead of services, sanitized as a URL. The downloadable example template and `stores-example.csv` were updated to match. Wrap any URL containing a comma (e.g. `?q=lat,lng`) in quotes so it stays a single CSV field.
