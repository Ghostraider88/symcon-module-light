# Changelog

## 1.1 – Build 3 – 2026-10-01

- Replace the Scene Control object-tree picker with a dropdown of existing Scene Control instances to avoid the console's "Node does not exist - Get Parent" error in that dialog.
- Show stale controller IDs as disabled choices requiring reselection; reject invalid selection callbacks.

## 1.1 – Build 2 – 2026-10-01

- Select and detect one light instance instead of scanning a category.
- Prefer native RGB color and Kelvin controls over alternate color outputs and Mired presets.
- Correct captured and previously stored colors in the profile editor; unset colors no longer become black.
- Remove duplicate seasonal fields from the light editor. Configure colors and Kelvin in the profile table.
- Select actual Scene Control scene names and suggest its ActiveScene variable automatically.
- Explain scene priority and resuming ambient control in the configuration form.

## 1.0 – Build 1 – 2026-10-01

- Initial dynamic ambient lighting, light profiles, and Scene Control integration.
