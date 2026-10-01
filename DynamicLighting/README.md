# Dynamic Lighting

General-purpose light automation for IP-Symcon 8.1 and later. Configure one instance per room, area, floor, or outdoor zone.

- Ambient control uses a selected illuminance variable, optional enable boolean, configurable lux curve, and daily time window.
- Choose a room or area category and discover candidate light outputs from their Symcon profiles, including `~Switch`, `~Intensity`, `~HexColor`, and `~TWColor`. Review discovered targets before applying the configuration.
- Create, rename, and delete named light profiles. Select the active profile and configure separate color and color-temperature values for each light/profile combination.
- Set a light to the desired color, then click **Take over current color** on its light/profile row to copy that value into the profile.
- Add, edit, and delete light and profile settings. Each target supports optional switch, brightness (0-100 or 0-255), color, and color-temperature variables plus brightness caps.
- Optional Scene Control instance, TV scene trigger, and additional boolean triggers. Other activated scenes hold as manual overrides until ambient mode is resumed.

Defaults follow the reference script's 300-2500 lux curve and requested 16:00-23:00 schedule. Observed Scene Control numbering: 1=Off, 2=Ambiente, 3=Abendessen, 4=Party, 5=Fernsehen.

When TV turns off, ambient control resumes only during the time window, when enabled and below the off threshold. Otherwise the configured Off scene runs. The module follows repository strict-module and visualization conventions; it does not enable archive logging or create other instances.
