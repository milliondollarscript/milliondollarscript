# Third-Party Notices

Million Dollar Script includes the following third-party software in its distributed plugin package.

## OpenLayers

- Version: 10.9.0
- Purpose: interactive and tiled grid display
- Project: <https://openlayers.org/>
- Source release: <https://github.com/openlayers/openlayers/releases/tag/v10.9.0>
- Source archive: `v10.9.0-package.zip`
- Source archive SHA-256: `eaece1938f506b18ad72f4fc7e97fe80475070d5e864d3dab2ab0ed1a30b6a7f`
- License: BSD 2-Clause

Distributed files:

| File | SHA-256 | Provenance |
|---|---|---|
| `assets/mds3/vendor/ol/ol.js` | `3e437d33dabfbefdabf9ba82ded5e4f5b473c186ecd02fce539457699d034fa6` | Upstream `dist/ol.js` with only its `sourceMappingURL` comment removed because source maps are not distributed. |
| `assets/mds3/vendor/ol/ol.css` | `abc8afd72cc10bd29cc143f443bae4a6804bd3cb3fb262e6b6a6bc6c924ea34f` | Exact upstream `ol.css`. |
| `assets/mds3/vendor/ol/LICENSE.md` | `6c4347b83a8c9feef18d57b18e3b6c44cf901b3c344a4a1fbd837e421555ab8e` | Exact upstream license text. |

The OpenLayers license is included at `assets/mds3/vendor/ol/LICENSE.md`.

## Platform Dependencies

The plugin does not ship Composer runtime packages or load JavaScript libraries from third-party CDNs. WordPress-provided browser dependencies are loaded through registered WordPress handles and remain governed by the WordPress installation that supplies them.
