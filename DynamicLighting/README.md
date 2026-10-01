# Dynamic Lighting

General-purpose light automation for IP-Symcon 9.1 and later. Configure one instance per room, area, floor, or outdoor zone.

- Ambient control uses a selected illuminance variable, optional enable boolean, configurable lux curve, and daily time window.
- Select a single light instance and click **Detect and add selected light**. Its direct child variables are identified by idents and profiles. Native RGB color and Kelvin outputs take priority over alternate color outputs; Mired temperatures and presets are not selected as Kelvin outputs. Add further lights, review the mappings, and apply changes before configuring profile values. Repeating discovery updates the mapping while preserving the configured name and brightness limits. Detection and color capture preserve other pending edits in their respective tables.
- Create, rename, and delete named light profiles. Select the active profile and configure separate color and color-temperature values for each light/profile combination.
- Set a light to the desired color, then click **Take over current color** on its light/profile row to copy that value into the profile.
- Add, edit, and delete light and profile settings. Each target supports optional switch, brightness (0-100 or 0-255), color, and color-temperature variables plus brightness caps.
- Select an optional Scene Control instance to load its actual scene names into the Off, TV, and boolean-trigger dropdowns. The `ActiveScene` variable is suggested automatically when selecting the instance; save the configuration to enable monitoring. TV has priority while its boolean is true. Other activated scenes pause ambient control until **Resume ambient control** is pressed. A boolean trigger starts its selected scene when true; switching it back to false does not automatically resume ambient control.

Color and Kelvin values are configured only in **Profile settings by light**, without duplicate seasonal fields in the light editor. Capturing a color stores a numeric RGB value for the form; older `#RRGGBB` settings are converted for display. Transparent color (`-1`) leaves the device color unchanged. Legacy seasonal values are offered as profile rows when mapped to the original default profiles.

Scene Control uses a dropdown of existing controllers with their names and IDs, avoiding the console object-tree selection dialog. If a saved controller is unavailable, select an existing controller or **No Scene Control**, then apply changes. The module does not silently rewrite the saved controller selection.

Defaults follow the reference script's 300-2500 lux curve and requested 16:00-23:00 schedule. Observed Scene Control numbering: 1=Off, 2=Ambiente, 3=Abendessen, 4=Party, 5=Fernsehen.

When TV turns off, ambient control resumes only during the time window, when enabled and below the off threshold. Otherwise the configured Off scene runs. The module follows repository strict-module and visualization conventions; it does not enable archive logging or create other instances.
