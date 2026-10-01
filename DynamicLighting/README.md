# Dynamic Lighting

General purpose light automation for IP-Symcon 8.1 and later. Configure one instance per room, area, floor, or outdoor zone.

- Ambient control uses a selected lux variable, optional enable boolean, configurable lux curve, and daily time window.
- Multiple targets support optional switch, brightness (0–100 or 0–255), color, and color temperature variables.
- Per-target brightness caps and seasonal values for spring, summer, autumn, and winter.
- Optional Scene Control instance, TV scene trigger, and additional boolean triggers. Other activated scenes hold as manual overrides until ambient mode is resumed.

Defaults follow the reference script's 300–2500 lux curve and requested 16:00–23:00 schedule. Observed Scene Control numbering: 1=Off, 2=Ambiente, 3=Abendessen, 4=Party, 5=Fernsehen.

When TV turns off, ambient control resumes only during the time window, when enabled and below the off threshold. Otherwise the configured Off scene runs. The module follows repository strict-module and visualization conventions; it does not enable archive logging or create other instances.