<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/VisuStyle.php';
require_once __DIR__ . '/../libs/VisuState.php';
require_once __DIR__ . '/../libs/VisuDiagnostic.php';
require_once __DIR__ . '/../libs/VisuCapability.php';

class DynamicLighting extends IPSModuleStrict
{
    private const AMBIENT = 0;
    private const OFF = 1;
    private const MANUAL = 2;
    private const TV = 3;
    private const SCENE_CONTROL_MODULE_ID = '{87F46796-CC43-442D-94FD-AAA0BD8D9F54}';
    private const PROFILE_SPRING = '{4AD0B7A1-5A2B-4E53-A261-11E8FC522101}';
    private const PROFILE_SUMMER = '{4AD0B7A1-5A2B-4E53-A261-11E8FC522102}';
    private const PROFILE_AUTUMN = '{4AD0B7A1-5A2B-4E53-A261-11E8FC522103}';
    private const PROFILE_WINTER = '{4AD0B7A1-5A2B-4E53-A261-11E8FC522104}';

    public function Create(): void
    {
        parent::Create();
        $this->SetVisualizationType(1);
        $this->RegisterPropertyInteger('LuxVariableID', 0);
        $this->RegisterPropertyInteger('EnableVariableID', 0);
        $this->RegisterPropertyInteger('BrightLux', 300);
        $this->RegisterPropertyInteger('DarkLux', 2500);
        $this->RegisterPropertyString('StartTime', '16:00');
        $this->RegisterPropertyString('EndTime', '23:00');
        $this->RegisterPropertyInteger('DiscoveryInstanceID', 0);
        $this->RegisterPropertyString('Profiles', json_encode([
            ['ProfileID' => self::PROFILE_SPRING, 'Name' => 'Frühling'],
            ['ProfileID' => self::PROFILE_SUMMER, 'Name' => 'Sommer'],
            ['ProfileID' => self::PROFILE_AUTUMN, 'Name' => 'Herbst'],
            ['ProfileID' => self::PROFILE_WINTER, 'Name' => 'Winter']
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->RegisterPropertyString('ActiveProfile', self::PROFILE_WINTER);
        $this->RegisterPropertyString('TargetProfiles', '[]');
        $this->RegisterPropertyString('EditProfileID', '');
        $this->RegisterAttributeString('TargetProfileData', '');
        $this->RegisterAttributeString('SelectedProfileID', '');
        // Kept for existing configurations; new configurations use Profiles and TargetProfiles.
        $this->RegisterPropertyString('Season', 'winter');
        $this->RegisterPropertyString('Targets', '[]');
        $this->RegisterPropertyInteger('SceneControlID', 0);
        $this->RegisterPropertyInteger('ActiveSceneID', 0);
        $this->RegisterPropertyInteger('OffScene', 1);
        $this->RegisterPropertyInteger('TVVariableID', 0);
        $this->RegisterPropertyInteger('TVScene', 5);
        $this->RegisterPropertyString('SceneTriggers', '[]');
        $this->RegisterTimer('EvaluateTimer', 0, 'DLT_EvaluateAndRefresh($_IPS[\'TARGET\']);');
    }

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadForm();
        $profiles = $this->Profiles();
        $profileOptions = [];
        foreach ($profiles as $profile) {
            $name = trim((string)($profile['Name'] ?? ''));
            $profileID = (string)($profile['ProfileID'] ?? '');
            if ($name !== '' && $profileID !== '') {
                $profileOptions[] = ['caption' => $name, 'value' => $profileID];
            }
        }
        if ($profileOptions === []) {
            $profileOptions[] = ['caption' => $this->Translate('Create a profile first'), 'value' => ''];
        }
        $targetOptions = [];
        foreach ($this->Targets() as $target) {
            $name = trim((string)($target['Name'] ?? ''));
            if ($name !== '') {
                $targetOptions[] = ['caption' => $name, 'value' => $name];
            }
        }
        $this->SetSelectOptions($form['elements'], 'ActiveProfile', $profileOptions);
        $this->SetListColumnOptions($form['elements'], 'TargetProfiles', 'TargetName', $targetOptions);
        $this->SetListColumnOptions($form['elements'], 'TargetProfiles', 'ProfileID', $profileOptions);
        $this->SetListFormFieldOptions($form['elements'], 'TargetProfiles', 'TargetName', $targetOptions);
        $editProfileID = $this->EditingProfileID();
        $profileValues = $this->TargetProfiles();
        if ($this->ReadAttributeString('TargetProfileData') !== '') {
            $profileValues = array_values(array_filter($profileValues, static fn (array $row): bool => (string)($row['ProfileID'] ?? '') === $editProfileID));
        }
        $this->SetSelectOptions($form['elements'], 'EditProfileID', $profileOptions);
        $this->SetFormField($form['elements'], 'EditProfileID', 'value', $editProfileID);
        $this->SetFormField($form['elements'], 'TargetProfiles', 'values', $profileValues);
        $sceneControlID = $this->ReadPropertyInteger('SceneControlID');
        $invalidController = $sceneControlID !== 0 && !in_array($sceneControlID, IPS_GetInstanceListByModuleID(self::SCENE_CONTROL_MODULE_ID), true);
        $this->SetFormField($form['elements'], 'ResetSceneControlSelection', 'visible', $invalidController);
        $sceneOptions = $this->SceneOptions($this->ReadPropertyInteger('SceneControlID'));
        $this->SetSelectOptions($form['elements'], 'OffScene', $sceneOptions);
        $this->SetListColumnOptions($form['elements'], 'SceneTriggers', 'SceneNumber', $sceneOptions);
        $this->SetListFormFieldOptions($form['elements'], 'SceneTriggers', 'SceneNumber', $sceneOptions);
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function SelectProfileForEditing(string $profileID): void
    {
        if (!in_array($profileID, array_column($this->Profiles(), 'ProfileID'), true)) return;
        $all = $this->TargetProfiles();
        $stored = $this->ReadAttributeString('TargetProfileData');
        if ($stored === '') {
            $this->WriteAttributeString('TargetProfileData', json_encode($all, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }
        $values = array_values(array_filter($all, static fn (array $row): bool => (string)($row['ProfileID'] ?? '') === $profileID));
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function AddProfileRow(string $rowJSON, string $profileID): void
    {
        $row = json_decode($rowJSON, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($row) || !in_array($profileID, array_column($this->Profiles(), 'ProfileID'), true)) return;
        $all = $this->TargetProfiles();
        $row['ProfileID'] = $profileID;
        $row['ColorValue'] = $this->ColorToInteger($row['ColorValue'] ?? null) ?? -1;
        $row['Temperature'] = max(0, (int)($row['Temperature'] ?? 0));
        $row['MinBrightness'] = (int)($row['MinBrightness'] ?? -1);
        $row['MaxBrightness'] = (int)($row['MaxBrightness'] ?? -1);
        $row['Capture'] = $this->Translate('Take over current color');
        $all[] = $row;
        $this->WriteAttributeString('TargetProfileData', json_encode($all, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $values = array_values(array_filter($all, static fn (array $entry): bool => (string)($entry['ProfileID'] ?? '') === $profileID));
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function DiscoverLights(int $lightInstanceID = 0, string $currentTargetsJSON = ''): void
    {
        if ($lightInstanceID === 0) {
            $lightInstanceID = $this->ReadPropertyInteger('DiscoveryInstanceID');
        }
        if ($lightInstanceID <= 0 || !IPS_InstanceExists($lightInstanceID) || $lightInstanceID === $this->InstanceID) {
            $this->UpdateFormField('DiscoveryStatus', 'caption', $this->Translate('Choose a light instance first.'));
            return;
        }
        $candidate = $this->ScanLightInstance($lightInstanceID);
        if (max($candidate['SwitchID'], $candidate['BrightnessID'], $candidate['ColorID'], $candidate['TemperatureID']) === 0) {
            $this->UpdateFormField('DiscoveryStatus', 'caption', $this->Translate('No supported light controls found in this instance.'));
            return;
        }
        $existing = $currentTargetsJSON === '' ? $this->Targets() : json_decode($currentTargetsJSON, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($existing)) throw new InvalidArgumentException('Invalid light target list.');
        $updated = false;
        foreach ($existing as &$target) {
            foreach (['SwitchID', 'BrightnessID', 'ColorID', 'TemperatureID'] as $key) {
                $variableID = (int)($target[$key] ?? 0);
                if ($variableID > 0 && IPS_VariableExists($variableID)
                    && IPS_GetObject($variableID)['ParentID'] === $lightInstanceID) {
                    // Retain the user's name and brightness limits when refreshing mappings.
                    foreach (['SwitchID', 'BrightnessID', 'BrightnessScale', 'ColorID', 'TemperatureID'] as $field) {
                        $target[$field] = $candidate[$field];
                    }
                    $updated = true;
                    break;
                }
            }
            if ($updated) break;
        }
        unset($target);
        if (!$updated) {
            $existing[] = $candidate;
        }
        $this->UpdateFormField('Targets', 'values', json_encode(array_values($existing), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $message = sprintf($this->Translate('Light detected: %s. Switch: %d, brightness: %d, color: %d, Kelvin: %d. Apply changes to save.'),
            $candidate['Name'], $candidate['SwitchID'], $candidate['BrightnessID'], $candidate['ColorID'], $candidate['TemperatureID']);
        $this->UpdateFormField('DiscoveryStatus', 'caption', $message);
    }

    public function CaptureCurrentColor(string $targetName, string $profileID, string $currentSettingsJSON = ''): void
    {
        if ($targetName === '' || $profileID === '') {
            $this->UpdateFormField('DiscoveryStatus', 'caption', $this->Translate('Select a light and a profile first.'));
            return;
        }
        $target = null;
        foreach ($this->Targets() as $candidate) {
            if ((string)($candidate['Name'] ?? '') === $targetName) {
                $target = $candidate;
                break;
            }
        }
        $colorID = (int)($target['ColorID'] ?? 0);
        if ($colorID <= 0 || !IPS_VariableExists($colorID)) {
            $this->UpdateFormField('DiscoveryStatus', 'caption', $this->Translate('The selected light has no color variable.'));
            return;
        }
        $colorValue = $this->ColorToInteger(GetValue($colorID));
        if ($colorValue === null) {
            $this->UpdateFormField('DiscoveryStatus', 'caption', $this->Translate('The color variable does not contain a valid RGB color.'));
            return;
        }
        $currentSettings = $currentSettingsJSON === '' ? null : json_decode($currentSettingsJSON, true, 512, JSON_THROW_ON_ERROR);
        $currentRow = is_array($currentSettings) && !array_is_list($currentSettings) ? $currentSettings : [];
        $currentRow['TargetName'] = $targetName;
        $currentRow['ProfileID'] = $profileID;
        $currentRow['ColorValue'] = $colorValue;
        $settings = $this->TargetProfiles();
        if (is_array($currentSettings) && array_is_list($currentSettings)) {
            foreach ($this->NormalizeProfileRows($currentSettings) as $pendingRow) {
                if ((string)($pendingRow['ProfileID'] ?? '') !== $profileID) continue;
                $pendingMatch = false;
                foreach ($settings as &$setting) {
                    if ((string)($setting['TargetName'] ?? '') === (string)($pendingRow['TargetName'] ?? '')
                        && (string)($setting['ProfileID'] ?? '') === $profileID) {
                        $setting = array_replace($setting, $pendingRow);
                        $pendingMatch = true;
                        break;
                    }
                }
                unset($setting);
                if (!$pendingMatch) $settings[] = $pendingRow;
            }
        }
        $updated = false;
        foreach ($settings as &$setting) {
            if ((string)($setting['TargetName'] ?? '') === $targetName && (string)($setting['ProfileID'] ?? '') === $profileID) {
                $setting = array_replace($setting, $currentRow);
                $updated = true;
                break;
            }
        }
        unset($setting);
        if (!$updated) {
            $settings[] = array_replace([
                'TargetName' => $targetName,
                'ProfileID' => $profileID,
                'ColorValue' => $colorValue,
                'Temperature' => 0,
                'MinBrightness' => -1,
                'MaxBrightness' => -1,
                'Capture' => 'Übernehmen'
            ], $currentRow);
        }
        foreach ($settings as &$setting) {
            $setting['Capture'] = 'Übernehmen';
        }
        unset($setting);
        $this->WriteAttributeString('TargetProfileData', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $visibleSettings = array_values(array_filter($settings, static fn (array $setting): bool => (string)($setting['ProfileID'] ?? '') === $profileID));
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($visibleSettings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateFormField('DiscoveryStatus', 'caption', sprintf($this->Translate('Captured current color for %s / %s.'), $targetName, $this->ProfileName($profileID)));
    }

    public function SelectSceneControl(int $sceneControlID): void
    {
        if ($sceneControlID !== 0 && !in_array($sceneControlID, IPS_GetInstanceListByModuleID(self::SCENE_CONTROL_MODULE_ID), true)) {
            throw new InvalidArgumentException('Please select an existing Scene Control instance.');
        }
        $this->UpdateFormField('SceneControlID', 'value', (string)$sceneControlID);
        $this->UpdateFormField('ResetSceneControlSelection', 'visible', 'false');
        $options = $this->SceneOptions($sceneControlID);
        $this->UpdateFormField('OffScene', 'options', json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $form = $this->LoadForm();
        $this->SetListColumnOptions($form['elements'], 'SceneTriggers', 'SceneNumber', $options);
        $this->SetListFormFieldOptions($form['elements'], 'SceneTriggers', 'SceneNumber', $options);
        foreach ($form['elements'] as $panel) {
            foreach ($panel['items'] ?? [] as $element) {
                if (($element['name'] ?? '') === 'SceneTriggers') {
                    $this->UpdateFormField('SceneTriggers', 'columns', json_encode($element['columns'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                    $this->UpdateFormField('SceneTriggers', 'form', json_encode($element['form'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                }
            }
        }
        $activeID = 0;
        if ($sceneControlID > 0 && IPS_InstanceExists($sceneControlID)) {
            foreach (IPS_GetChildrenIDs($sceneControlID) as $childID) {
                $object = IPS_GetObject($childID);
                if ($object['ObjectType'] === 2 && $object['ObjectIdent'] === 'ActiveScene') {
                    $activeID = $childID;
                    break;
                }
            }
        }
        $this->UpdateFormField('ActiveSceneID', 'value', (string)$activeID);
    }

    private function ActiveSceneNumber(string $name): ?int
    {
        $matches = [];
        foreach ($this->SceneOptions($this->ReadPropertyInteger('SceneControlID')) as $option) {
            if ($option['value'] > 0 && ($option['enabled'] ?? true) && $option['caption'] === $name) {
                $matches[] = $option['value'];
            }
        }
        return count($matches) === 1 ? $matches[0] : null;
    }

    private function HandleActiveScene(string $name): void
    {
        $active = $this->ActiveSceneNumber($name);
        // "Unknown" is a normal intermediate state while device values change.
        if ($active === null) return;
        $requested = (int)$this->GetBuffer('RequestedScene');
        $deadline = (int)$this->GetBuffer('RequestedSceneUntil');
        $this->SetBuffer('RequestedScene', '');
        $this->SetBuffer('RequestedSceneUntil', '');
        if ($active === $requested && $deadline >= time()) return;
        $this->SetValue('Mode', self::MANUAL);
        $this->SetValue('Status', $this->Translate('Manual scene') . ': ' . $name);
        $this->SetBuffer('OutputOff', '0');
    }

    private function SceneOptions(int $sceneControlID): array
    {
        $options = [['caption' => $this->Translate('No scene'), 'value' => 0]];
        if ($sceneControlID > 0 && IPS_InstanceExists($sceneControlID)
            && (IPS_GetInstance($sceneControlID)['ModuleInfo']['ModuleName'] ?? '') === 'SceneControl') {
            foreach (IPS_GetChildrenIDs($sceneControlID) as $childID) {
                $object = IPS_GetObject($childID);
                if ($object['ObjectType'] === 2 && preg_match('/^Scene([1-9][0-9]*)$/', $object['ObjectIdent'], $matches) === 1) {
                    $options[] = ['caption' => $object['ObjectName'], 'value' => (int)$matches[1]];
                }
            }
        }
        usort($options, static fn (array $a, array $b): int => $a['value'] <=> $b['value']);
        $sceneNumbers = array_map(static fn (array $trigger): int => (int)($trigger['SceneNumber'] ?? 0), $this->SceneTriggers());
        foreach ([$this->ReadPropertyInteger('OffScene'), $this->ReadPropertyInteger('TVScene'), ...$sceneNumbers] as $number) {
            if ($number > 0 && !in_array($number, array_column($options, 'value'), true)) {
                $options[] = ['caption' => sprintf($this->Translate('Scene %d (select Scene Control)'), $number), 'value' => $number, 'enabled' => false];
            }
        }
        return $options;
    }

    private function SetFormField(array &$elements, string $name, string $field, mixed $value): void
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $name) $element[$field] = $value;
            if (isset($element['items']) && is_array($element['items'])) {
                $this->SetFormField($element['items'], $name, $field, $value);
            }
        }
        unset($element);
    }

    private function LoadForm(): array
    {
        $formJSON = file_get_contents(__DIR__ . '/form.json');
        if ($formJSON === false) throw new RuntimeException('Unable to read Dynamic Lighting configuration form.');
        $form = json_decode($formJSON, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($form) || !isset($form['elements']) || !is_array($form['elements'])) throw new RuntimeException('Invalid Dynamic Lighting configuration form.');
        return $form;
    }

    public function DeleteProfile(string $profileID): void
    {
        if ($this->ReadAttributeString('SelectedProfileID') === $profileID) {
            $this->WriteAttributeString('SelectedProfileID', '');
        }
        $settings = array_values(array_filter(
            $this->TargetProfiles(),
            static fn (array $setting): bool => (string)($setting['ProfileID'] ?? '') !== $profileID
        ));
        foreach ($settings as &$setting) {
            $setting['Capture'] = 'Übernehmen';
        }
        unset($setting);
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $this->WriteAttributeString('TargetProfileData', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $remainingProfiles = array_values(array_filter(
            $this->Profiles(),
            static fn (array $profile): bool => (string)($profile['ProfileID'] ?? '') !== $profileID
        ));
        $options = [];
        foreach ($remainingProfiles as $profile) {
            $name = trim((string)($profile['Name'] ?? ''));
            $id = (string)($profile['ProfileID'] ?? '');
            if ($name !== '' && $id !== '') {
                $options[] = ['caption' => $name, 'value' => $id];
            }
        }
        $this->UpdateFormField('ActiveProfile', 'options', json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateFormField('EditProfileID', 'options', json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        if ($this->ReadPropertyString('ActiveProfile') === $profileID) {
            $this->UpdateFormField('ActiveProfile', 'value', (string)($options[0]['value'] ?? ''));
        }
        $editProfileID = $this->EditingProfileID();
        if ($editProfileID === $profileID) {
            $editProfileID = (string)($options[0]['value'] ?? '');
            $this->UpdateFormField('EditProfileID', 'value', $editProfileID);
        }
        $visibleSettings = array_values(array_filter($settings, static fn (array $setting): bool => (string)($setting['ProfileID'] ?? '') === $editProfileID));
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($visibleSettings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SyncTargetProfileData();
        if ($this->RegisterVariableString('Status', $this->Translate('Status'), '', 10)) $this->SetValue('Status', '');
        if ($this->RegisterVariableFloat('Illuminance', $this->Translate('Current illuminance'), '', 20)) $this->SetValue('Illuminance', 0.0);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Illuminance'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' lx', 'DIGITS' => 0]);
        if ($this->RegisterVariableInteger('CalculatedBrightness', $this->Translate('Calculated brightness'), '', 30)) $this->SetValue('CalculatedBrightness', 0);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('CalculatedBrightness'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %', 'MIN' => 0, 'MAX' => 100, 'DIGITS' => 0]);
        if ($this->RegisterVariableInteger('Profile', $this->Translate('Light profile'), '', 35)) {
            $this->SetValue('Profile', $this->ProfileOptionValue($this->EffectiveActiveProfile()));
        }
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Profile'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS' => json_encode($this->ProfilePresentationOptions(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'DISPLAY' => 2, 'LAYOUT' => 1
        ]);
        $this->MaintainAction('Profile', true);
        $selectedProfile = $this->EffectiveActiveProfile();
        $storedProfile = $this->ReadAttributeString('SelectedProfileID');
        if ($storedProfile !== '' && !in_array($storedProfile, array_column($this->Profiles(), 'ProfileID'), true)) {
            $this->WriteAttributeString('SelectedProfileID', '');
        }
        $profileOptionValue = $this->ProfileOptionValue($selectedProfile);
        if ($this->GetValue('Profile') !== $profileOptionValue) $this->SetValue('Profile', $profileOptionValue);
        if ($this->RegisterVariableInteger('Mode', $this->Translate('Mode'), '', 40)) $this->SetValue('Mode', self::AMBIENT);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Mode'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS' => json_encode([
                ['Value' => self::AMBIENT, 'Caption' => $this->Translate('Ambient'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => self::OFF, 'Caption' => $this->Translate('Off'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => self::MANUAL, 'Caption' => $this->Translate('Manual scene'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => self::TV, 'Caption' => $this->Translate('Priority scene'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1]
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'DISPLAY' => 2, 'LAYOUT' => 1
        ]);
        $this->MaintainAction('Mode', true);
        $this->RegisterMessage($this->GetIDForIdent('Mode'), VM_UPDATE);
        foreach ([$this->ReadPropertyInteger('LuxVariableID'), $this->ReadPropertyInteger('EnableVariableID'), $this->ReadPropertyInteger('TVVariableID'), $this->ReadPropertyInteger('ActiveSceneID')] as $id) {
            if ($id > 0 && IPS_VariableExists($id)) $this->RegisterMessage($id, VM_UPDATE);
        }
        foreach ($this->SceneTriggers() as $trigger) {
            $id = (int)($trigger['VariableID'] ?? 0);
            if ($id > 0 && IPS_VariableExists($id)) $this->RegisterMessage($id, VM_UPDATE);
        }
        $this->SetTimerInterval('EvaluateTimer', 60000);
        $valid = $this->ConfigurationIsValid();
        $this->SetStatus($valid ? 102 : 104);
        if ($valid) $this->Evaluate();
        else $this->SetValue('Status', $this->Translate('Configuration required'));
        $this->UpdateVisualizationValue($this->VisualizationPayload());
    }

    public function MessageSink(int $timestamp, int $senderID, int $message, array $data): void
    {
        if ($message !== VM_UPDATE) return;
        if ($senderID === $this->ReadPropertyInteger('ActiveSceneID')) {
            if (!(bool)($data[1] ?? false)) return;
            $this->HandleActiveScene((string)GetValue($senderID));
        }
        foreach ($this->SceneTriggers() as $trigger) {
            if ((int)($trigger['VariableID'] ?? 0) === $senderID) {
                if ((bool)GetValue($senderID)) {
                    $luxID = $this->ReadPropertyInteger('LuxVariableID');
                    $lux = $luxID > 0 && IPS_VariableExists($luxID) ? (float)GetValue($luxID) : INF;
                    if ($this->TriggerMayActivate($trigger, $lux)) {
                        $sceneNumber = (int)($trigger['SceneNumber'] ?? 0);
                        $this->CallScene($sceneNumber);
                        if (!empty($trigger['Priority'])) {
                            $this->SetBuffer('PrioritySceneNumber', (string)$sceneNumber);
                            $this->SetValue('Mode', self::TV);
                        } else {
                            $this->SetValue('Mode', self::MANUAL);
                        }
                    }
                } elseif (!empty($trigger['ResumeOnFalse']) && !$this->HasActiveSceneTrigger()) {
                    $this->SetBuffer('PrioritySceneNumber', '');
                    $this->SetValue('Mode', self::AMBIENT);
                }
            }
        }
        $this->Evaluate();
        $this->UpdateVisualizationValue($this->VisualizationPayload());
    }

    public function RequestAction(string $ident, mixed $value): void
    {
        if ($ident === 'ResumeAmbient' && $value === true) {
            $this->ResumeAmbient();
            return;
        }
        if ($ident === 'SwitchOff' && $value === true) {
            $this->SwitchOff();
            return;
        }
        if ($ident === 'Profile' && is_int($value)) {
            $profileID = $this->ProfileIDForOption($value);
            if ($profileID === null) throw new InvalidArgumentException('Select one of the configured lighting profiles.');
            $this->WriteAttributeString('SelectedProfileID', $profileID);
            $this->SetValue('Profile', $value);
            $this->EvaluateAndRefresh();
            return;
        }
        if ($ident !== 'Mode' || !is_int($value) || !in_array($value, [self::AMBIENT, self::OFF, self::MANUAL, self::TV], true)) {
            throw new InvalidArgumentException('Invalid mode action.');
        }
        $this->SetValue('Mode', $value === self::TV ? self::MANUAL : $value);
        if ($value === self::OFF) {
            $this->TurnLightsOff();
            $this->SetValue('Status', $this->Translate('Off'));
        } elseif ($value === self::TV) {
            $this->CallScene($this->ReadPropertyInteger('TVScene'));
            $this->SetValue('Status', $this->Translate('Priority scene active'));
        } elseif ($value === self::MANUAL) {
            $this->SetValue('Status', $this->Translate('Manual scene'));
        } else {
            $this->Evaluate();
        }
        $this->UpdateVisualizationValue($this->VisualizationPayload());
    }
    public function ResumeAmbient(): void
    {
        $this->SetValue('Mode', self::AMBIENT);
        $this->Evaluate();
        $this->UpdateVisualizationValue($this->VisualizationPayload());
    }

    public function SwitchOff(): void
    {
        $this->SetValue('Mode', self::OFF);
        $this->TurnLightsOff();
        $this->SetValue('Status', $this->Translate('Off'));
        $this->UpdateVisualizationValue($this->VisualizationPayload());
    }

    public function EvaluateAndRefresh(): void
    {
        $this->Evaluate();
        $this->UpdateVisualizationValue($this->VisualizationPayload());
    }
    public function Evaluate(): void
    {
        if (!$this->ConfigurationIsValid()) {
            $this->SetValue('Status', $this->Translate('Configuration required'));
            return;
        }
        $lux = (float)GetValue($this->ReadPropertyInteger('LuxVariableID'));
        $this->SetValue('Illuminance', $lux);
        $mode = (int)$this->GetValue('Mode');
        $priorityTrigger = $this->ActivePriorityTrigger($lux);
        if ($priorityTrigger !== null) {
            $priorityScene = (int)($priorityTrigger['SceneNumber'] ?? 0);
            if ((int)$this->GetBuffer('PrioritySceneNumber') !== $priorityScene) {
                $this->CallScene($priorityScene);
                $this->SetBuffer('PrioritySceneNumber', (string)$priorityScene);
            }
            if ($mode !== self::TV) $this->SetValue('Mode', self::TV);
            $this->SetValue('Status', $this->Translate('Priority scene active'));
            return;
        }
        if ($mode === self::TV) {
            $resumePriorityScene = false;
            foreach ($this->SceneTriggers() as $trigger) {
                if (!empty($trigger['Priority']) && !empty($trigger['ResumeOnFalse'])) {
                    $resumePriorityScene = true;
                    break;
                }
            }
            if ($resumePriorityScene) {
                $this->SetBuffer('PrioritySceneNumber', '');
                $this->SetValue('Mode', self::AMBIENT);
                $mode = self::AMBIENT;
            } else {
                $this->SetValue('Mode', self::MANUAL);
                return;
            }
        }
        if ($mode === self::MANUAL || $mode === self::OFF) return;
        if (!$this->WithinSchedule() || !$this->Enabled()) {
            $this->SetValue('CalculatedBrightness', 0);
            $this->TurnLightsOff();
            $this->SetValue('Status', !$this->Enabled() ? $this->Translate('Disabled') : $this->Translate('Outside schedule'));
            return;
        }
        $low = $this->ReadPropertyInteger('BrightLux');
        $high = $this->ReadPropertyInteger('DarkLux');
        if ($high <= $low) {
            $this->SetStatus(201);
            $this->SetValue('Status', $this->Translate('Invalid lux thresholds'));
            return;
        }
        $percent = $lux >= $high ? 0 : (int)round(max(0.0, min(1.0, ($high - $lux) / ($high - $low))) * 100);
        $this->SetValue('CalculatedBrightness', $percent);
        if ($percent === 0) {
            $this->TurnLightsOff();
            $this->SetValue('Status', $this->Translate('Bright enough'));
        } else {
            $this->ApplyTargets($percent);
            $this->SetValue('Status', $this->Translate('Ambient control active'));
        }
        $this->SetStatus(102);
    }

    private function ApplyTargets(int $percent): void
    {
        $this->SetBuffer('OutputOff', '0');
        $profileID = $this->EffectiveActiveProfile();
        foreach ($this->Targets() as $target) {
            $settings = $this->TargetProfile($target, $profileID);
            $targetMaximum = max(1, min(100, (int)($target['MaxBrightness'] ?? 100)));
            $targetMinimum = max(0, min($targetMaximum, (int)($target['MinBrightness'] ?? 0)));
            $maximumSetting = (int)($settings['MaxBrightness'] ?? -1);
            $minimumSetting = (int)($settings['MinBrightness'] ?? -1);
            $maximum = $maximumSetting >= 0 ? min(100, $maximumSetting) : $targetMaximum;
            $minimum = $minimumSetting >= 0 ? min($maximum, $minimumSetting) : min($maximum, $targetMinimum);
            $brightness = (int)round($minimum + ($maximum - $minimum) * $percent / 100);
            $id = (int)($target['BrightnessID'] ?? 0);
            if ($id > 0 && IPS_VariableExists($id)) {
                $scale = (int)($target['BrightnessScale'] ?? 100) === 255 ? 255 : 100;
                RequestAction($id, (int)round($brightness * $scale / 100));
            }
            $id = (int)($target['SwitchID'] ?? 0);
            if ($id > 0 && IPS_VariableExists($id)) RequestAction($id, $brightness > 0);
            if ($brightness === 0) continue;
            $id = (int)($target['ColorID'] ?? 0);
            $colorValue = $settings['ColorValue'] ?? null;
            $color = $this->ColorToInteger($colorValue);
            $id = (int)($target['TemperatureID'] ?? 0);
            $temperature = (int)($settings['Temperature'] ?? 0);
            if ($id > 0 && $temperature > 0 && IPS_VariableExists($id)) {
                RequestAction($id, $temperature);
            } else {
                $id = (int)($target['ColorID'] ?? 0);
                if ($id > 0 && $color !== null && IPS_VariableExists($id)) RequestAction($id, $color);
            }
        }
    }

    private function TurnLightsOff(): void
    {
        if ($this->GetBuffer('OutputOff') === '1') {
            $this->SetValue('CalculatedBrightness', 0);
            return;
        }
        $this->SetBuffer('OutputOff', '1');
        $control = $this->ReadPropertyInteger('SceneControlID');
        $scene = $this->ReadPropertyInteger('OffScene');
        if ($control > 0 && $scene > 0 && function_exists('SZS_CallScene')) {
            $this->CallScene($scene);
            $this->SetValue('CalculatedBrightness', 0);
            return;
        }
        foreach ($this->Targets() as $target) {
            $id = (int)($target['BrightnessID'] ?? 0);
            if ($id > 0 && IPS_VariableExists($id)) RequestAction($id, 0);
            $id = (int)($target['SwitchID'] ?? 0);
            if ($id > 0 && IPS_VariableExists($id)) RequestAction($id, false);
        }
        $this->SetValue('CalculatedBrightness', 0);
    }

    private function CallScene(int $number): void
    {
        $control = $this->ReadPropertyInteger('SceneControlID');
        if ($control <= 0 || $number <= 0) return;
        if (!function_exists('SZS_CallScene')) throw new RuntimeException('The Scene Control action SZS_CallScene is unavailable.');
        $this->SetBuffer('RequestedScene', (string)$number);
        $this->SetBuffer('RequestedSceneUntil', (string)(time() + 30));
        $this->SetBuffer('OutputOff', $number === $this->ReadPropertyInteger('OffScene') ? '1' : '0');
        SZS_CallScene($control, $number);
    }

    private function WithinSchedule(): bool
    {
        $start = $this->ParseTime($this->ReadPropertyString('StartTime'));
        $end = $this->ParseTime($this->ReadPropertyString('EndTime'));
        if ($start === null || $end === null) return false;
        $now = (int)date('H') * 60 + (int)date('i');
        if ($start === $end) return true;
        return $start < $end ? $now >= $start && $now < $end : $now >= $start || $now < $end;
    }

    private function ParseTime(string $time): ?int
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) return null;
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        return $hour <= 23 && $minute <= 59 ? $hour * 60 + $minute : null;
    }

    private function Enabled(): bool
    {
        $id = $this->ReadPropertyInteger('EnableVariableID');
        // The enable input is optional. If its variable was deleted, treat the stale ID as unset.
        return $id <= 0 || !IPS_VariableExists($id) || (bool)GetValue($id);
    }

    private function ConfigurationIsValid(): bool
    {
        $lux = $this->ReadPropertyInteger('LuxVariableID');
        if ($this->Targets() === []
            || !$this->VariableHasType($lux, [1, 2])
            || $this->ReadPropertyInteger('BrightLux') < 0
            || $this->ReadPropertyInteger('DarkLux') <= $this->ReadPropertyInteger('BrightLux')
            || $this->Profiles() === []
            || !in_array($this->ReadPropertyString('ActiveProfile'), array_column($this->Profiles(), 'ProfileID'), true)
            || $this->ParseTime($this->ReadPropertyString('StartTime')) === null
            || $this->ParseTime($this->ReadPropertyString('EndTime')) === null) {
            return false;
        }

        $enableID = $this->ReadPropertyInteger('EnableVariableID');
        $tvID = $this->ReadPropertyInteger('TVVariableID');
        if (($enableID > 0 && IPS_VariableExists($enableID) && !$this->VariableHasType($enableID, [0]))
            || ($tvID > 0 && !$this->VariableHasType($tvID, [0]))) {
            return false;
        }

        $sceneControlID = $this->ReadPropertyInteger('SceneControlID');
        if ($sceneControlID > 0) {
            if (!IPS_InstanceExists($sceneControlID) || !$this->VariableHasType($this->ReadPropertyInteger('ActiveSceneID'), [3])) {
                return false;
            }
            $sceneInfo = IPS_GetInstance($sceneControlID);
            if (($sceneInfo['ModuleInfo']['ModuleName'] ?? '') !== 'SceneControl') {
                return false;
            }
        } elseif ($tvID > 0 || $this->SceneTriggers() !== []) {
            return false;
        }

        foreach ($this->SceneTriggers() as $trigger) {
            if (!$this->VariableHasType((int)($trigger['VariableID'] ?? 0), [0])) {
                return false;
            }
        }

        foreach ($this->Targets() as $target) {
            $hasOutput = false;
            foreach (['SwitchID', 'BrightnessID', 'ColorID', 'TemperatureID'] as $key) {
                $hasOutput = $hasOutput || (int)($target[$key] ?? 0) > 0;
            }
            if (!$hasOutput) {
                return false;
            }
            foreach ([
                'SwitchID' => [0],
                'BrightnessID' => [1, 2],
                'ColorID' => [1],
                'TemperatureID' => [1]
            ] as $key => $types) {
                $id = (int)($target[$key] ?? 0);
                if ($id > 0 && !$this->VariableHasType($id, $types)) {
                    return false;
                }
            }
        }
        return true;
    }
    private function VariableHasType(int $id, array $types): bool
    {
        if ($id <= 0 || !IPS_VariableExists($id)) {
            return false;
        }
        $variable = IPS_GetVariable($id);
        return in_array((int)$variable['VariableType'], $types, true);
    }

    private function SetSelectOptions(array &$elements, string $name, array $options): void
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $name) {
                $element['options'] = $options;
            }
            if (isset($element['items']) && is_array($element['items'])) {
                $this->SetSelectOptions($element['items'], $name, $options);
            }
        }
        unset($element);
    }

    private function SetListColumnOptions(array &$elements, string $listName, string $columnName, array $options): void
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $listName && isset($element['columns']) && is_array($element['columns'])) {
                foreach ($element['columns'] as &$column) {
                    if (($column['name'] ?? '') === $columnName) {
                        $column['edit']['options'] = $options;
                    }
                }
                unset($column);
            }
            if (isset($element['items']) && is_array($element['items'])) {
                $this->SetListColumnOptions($element['items'], $listName, $columnName, $options);
            }
        }
        unset($element);
    }

    private function SetListFormFieldOptions(array &$elements, string $listName, string $fieldName, array $options): void
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $listName && isset($element['form']) && is_array($element['form'])) {
                $this->SetSelectOptions($element['form'], $fieldName, $options);
            }
            if (isset($element['items']) && is_array($element['items'])) {
                $this->SetListFormFieldOptions($element['items'], $listName, $fieldName, $options);
            }
        }
        unset($element);
    }

    private function ScanLightInstance(int $lightInstanceID): array
    {
        $candidate = [
            'Name' => IPS_GetObject($lightInstanceID)['ObjectName'],
            'SwitchID' => 0, 'BrightnessID' => 0, 'BrightnessScale' => 100,
            'MaxBrightness' => 100, 'MinBrightness' => 0, 'ColorID' => 0, 'TemperatureID' => 0
        ];
        $scores = ['SwitchID' => 0, 'BrightnessID' => 0, 'ColorID' => 0, 'TemperatureID' => 0];
        $children = IPS_GetChildrenIDs($lightInstanceID);
        sort($children);
        foreach ($children as $variableID) {
            $object = IPS_GetObject($variableID);
            if ($object['ObjectType'] !== 2) continue;
            $variable = IPS_GetVariable($variableID);
            if ((int)($variable['VariableAction'] ?? 0) <= 0 && (int)($variable['VariableCustomAction'] ?? 0) <= 0) continue;
            $profile = strtolower((string)(($variable['VariableCustomProfile'] ?? '') ?: ($variable['VariableProfile'] ?? '')));
            $ident = strtolower((string)$object['ObjectIdent']);
            $type = (int)$variable['VariableType'];
            $matches = [
                'SwitchID' => $type === 0 ? (in_array($ident, ['state', 'power', 'switch'], true) ? 100 : (str_contains($profile, 'switch') ? 80 : 0)) : 0,
                'BrightnessID' => in_array($type, [1, 2], true) ? ($ident === 'brightness' ? 100 : (str_contains($profile, 'intensity') ? 80 : 0)) : 0,
                'ColorID' => $type === 1 ? (in_array($ident, ['color', 'color_rgb', 'rgb'], true) ? 100 : (str_contains($profile, 'hexcolor') ? 80 : 0)) : 0,
                // Only use a Kelvin output. Mired outputs and presets cannot receive Kelvin values.
                'TemperatureID' => $type === 1 ? (in_array($ident, ['color_temp_kelvin', 'temperature_kelvin'], true) ? 100 : (str_contains($profile, 'twcolor') ? 80 : 0)) : 0
            ];
            foreach ($matches as $field => $score) {
                if ($score > $scores[$field]) {
                    $candidate[$field] = $variableID;
                    $scores[$field] = $score;
                    if ($field === 'BrightnessID') {
                        $candidate['BrightnessScale'] = str_contains($profile, '.255') ? 255 : 100;
                    }
                }
            }
        }
        return $candidate;
    }

    private function Profiles(): array
    {
        $rows = json_decode($this->ReadPropertyString('Profiles'), true);
        return is_array($rows) ? array_values(array_filter($rows, static fn (mixed $row): bool => is_array($row)
            && trim((string)($row['Name'] ?? '')) !== ''
            && trim((string)($row['ProfileID'] ?? '')) !== '')) : [];
    }

    private function TargetProfiles(): array
    {
        $stored = $this->ReadAttributeString('TargetProfileData');
        $rows = json_decode($stored !== '' ? $stored : $this->ReadPropertyString('TargetProfiles'), true);
        $rows = $this->NormalizeProfileRows(is_array($rows) ? $rows : []);
        // Offer legacy seasonal values in the profile table for the user to save.
        foreach ($this->Targets() as $target) {
            foreach ($this->Profiles() as $profile) {
                $season = $this->LegacySeasonName((string)$profile['ProfileID']);
                if ($season === '') continue;
                $color = $this->ColorToInteger($target[$season . 'Color'] ?? null);
                $temperature = (int)($target[$season . 'Temperature'] ?? 0);
                if (($color === null || $color === 0) && $temperature <= 0) continue;
                $found = false;
                foreach ($rows as $row) {
                    if (($row['TargetName'] ?? '') === ($target['Name'] ?? '') && ($row['ProfileID'] ?? '') === $profile['ProfileID']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) $rows[] = ['TargetName' => (string)($target['Name'] ?? ''), 'ProfileID' => $profile['ProfileID'],
                    'ColorValue' => $color ?? -1, 'Temperature' => $temperature, 'MinBrightness' => -1,
                    'MaxBrightness' => -1, 'Capture' => $this->Translate('Take over current color')];
            }
        }
        return $rows;
    }

    private function EditingProfileID(): string
    {
        $profileID = $this->ReadPropertyString('EditProfileID');
        if ($profileID !== '' && in_array($profileID, array_column($this->Profiles(), 'ProfileID'), true)) return $profileID;
        $active = $this->ReadPropertyString('ActiveProfile');
        return in_array($active, array_column($this->Profiles(), 'ProfileID'), true) ? $active : (string)($this->Profiles()[0]['ProfileID'] ?? '');
    }

    private function EffectiveActiveProfile(): string
    {
        $profileIDs = array_column($this->Profiles(), 'ProfileID');
        $selected = $this->ReadAttributeString('SelectedProfileID');
        if ($selected !== '' && in_array($selected, $profileIDs, true)) return $selected;
        $configured = $this->ReadPropertyString('ActiveProfile');
        return in_array($configured, $profileIDs, true) ? $configured : (string)($profileIDs[0] ?? '');
    }

    private function ProfileOptionValue(string $profileID): int
    {
        $index = array_search($profileID, array_column($this->Profiles(), 'ProfileID'), true);
        return $index === false ? 0 : (int)$index;
    }

    private function ProfileIDForOption(int $value): ?string
    {
        $profileID = array_column($this->Profiles(), 'ProfileID')[$value] ?? null;
        return $profileID === null ? null : (string)$profileID;
    }

    private function ProfilePresentationOptions(): array
    {
        $options = [];
        foreach ($this->Profiles() as $index => $profile) {
            $options[] = [
                'Value' => (int)$index,
                'Caption' => (string)$profile['Name'],
                'IconValue' => '', 'IconActive' => false, 'Color' => -1
            ];
        }
        return $options;
    }

    private function SyncTargetProfileData(): void
    {
        $stored = $this->ReadAttributeString('TargetProfileData');
        $propertyRows = json_decode($this->ReadPropertyString('TargetProfiles'), true);
        $propertyRows = $this->NormalizeProfileRows(is_array($propertyRows) ? $propertyRows : []);
        if ($stored === '') {
            $all = $propertyRows;
        } else {
            $all = json_decode($stored, true);
            $all = $this->NormalizeProfileRows(is_array($all) ? $all : []);
            $editing = $this->EditingProfileID();
            if ($editing !== '') {
                $all = array_values(array_filter($all, static fn (array $row): bool => (string)($row['ProfileID'] ?? '') !== $editing));
                foreach ($propertyRows as $row) {
                    if ((string)($row['ProfileID'] ?? '') === $editing) $all[] = $row;
                }
            }
        }
        $this->WriteAttributeString('TargetProfileData', json_encode(array_values($all), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function NormalizeProfileRows(array $rows): array
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        foreach ($rows as &$row) {
            $row['ColorValue'] = $this->ColorToInteger($row['ColorValue'] ?? null) ?? -1;
            $row['MinBrightness'] = (int)($row['MinBrightness'] ?? -1);
            $row['MaxBrightness'] = (int)($row['MaxBrightness'] ?? -1);
            $row['Capture'] = $this->Translate('Take over current color');
        }
        unset($row);
        return $rows;
    }

    private function TargetProfile(array $target, string $profileID): array
    {
        foreach ($this->TargetProfiles() as $setting) {
            if ((string)($setting['TargetName'] ?? '') === (string)($target['Name'] ?? '')
                && (string)($setting['ProfileID'] ?? '') === $profileID) {
                return $setting;
            }
        }
        return [];
    }

    private function ColorToInteger(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return $value >= 0 && $value <= 0xFFFFFF ? (int)$value : null;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^(?:#|0x)([0-9a-f]{6})$/i', $value, $matches) === 1) return (int)hexdec($matches[1]);
        if (ctype_digit($value)) return $this->ColorToInteger((int)$value);
        return preg_match('/^[0-9a-f]{6}$/i', $value) === 1 ? (int)hexdec($value) : null;
    }

    private function LegacySeasonName(string $profileID): string
    {
        return match ($profileID) {
            self::PROFILE_SPRING => 'Spring',
            self::PROFILE_SUMMER => 'Summer',
            self::PROFILE_AUTUMN => 'Autumn',
            self::PROFILE_WINTER => 'Winter',
            default => ''
        };
    }

    private function ProfileName(string $profileID): string
    {
        foreach ($this->Profiles() as $profile) {
            if ((string)($profile['ProfileID'] ?? '') === $profileID) {
                return (string)($profile['Name'] ?? '');
            }
        }
        return $profileID;
    }
    private function Targets(): array
    {
        $rows = json_decode($this->ReadPropertyString('Targets'), true);
        return is_array($rows) ? $rows : [];
    }

    private function SceneTriggers(): array
    {
        $rows = json_decode($this->ReadPropertyString('SceneTriggers'), true);
        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        foreach ($rows as &$row) {
            if (!isset($row['SceneNumber']) && isset($row['Scene'])) $row['SceneNumber'] = $row['Scene'];
            $row['Priority'] = (bool)($row['Priority'] ?? false);
            $row['ResumeOnFalse'] = (bool)($row['ResumeOnFalse'] ?? false);
            $row['OnlyWhenAmbientActive'] = (bool)($row['OnlyWhenAmbientActive'] ?? false);
        }
        unset($row);
        $tvID = $this->ReadPropertyInteger('TVVariableID');
        if ($tvID > 0 && !array_filter($rows, static fn (array $row): bool => (int)($row['VariableID'] ?? 0) === $tvID)) {
            $rows[] = ['VariableID' => $tvID, 'SceneNumber' => $this->ReadPropertyInteger('TVScene'), 'Priority' => true,
                'ResumeOnFalse' => true, 'OnlyWhenAmbientActive' => false];
        }
        return $rows;
    }

    private function HasActiveSceneTrigger(): bool
    {
        foreach ($this->SceneTriggers() as $trigger) {
            $id = (int)($trigger['VariableID'] ?? 0);
            if ($id > 0 && IPS_VariableExists($id) && (bool)GetValue($id)) return true;
        }
        return false;
    }

    private function ActivePriorityTrigger(float $lux): ?array
    {
        foreach ($this->SceneTriggers() as $trigger) {
            $id = (int)($trigger['VariableID'] ?? 0);
            if (!empty($trigger['Priority']) && $id > 0 && IPS_VariableExists($id) && (bool)GetValue($id)
                && $this->TriggerMayActivate($trigger, $lux)) return $trigger;
        }
        return null;
    }

    private function TriggerMayActivate(array $trigger, float $lux): bool
    {
        if (empty($trigger['OnlyWhenAmbientActive'])) return true;
        $brightLux = $this->ReadPropertyInteger('BrightLux');
        $darkLux = $this->ReadPropertyInteger('DarkLux');
        if (!$this->WithinSchedule() || !$this->Enabled() || $darkLux <= $brightLux) return false;
        $percent = (int)round(max(0.0, min(1.0, ($darkLux - $lux) / ($darkLux - $brightLux))) * 100);
        return $percent > 0;
    }


    private function VisualizationPayload(): string
    {
        $mode = (int)$this->GetValue('Mode');
        $label = match ($mode) {
            self::OFF => $this->Translate('Off'),
            self::MANUAL => $this->Translate('Manual scene'),
            self::TV => $this->Translate('Priority scene'),
            default => $this->Translate('Ambient')
        };
        $checks = [
            ModuleVisuDiagnostic::check('lux', $this->Translate('Illuminance input'), $this->ReadPropertyInteger('LuxVariableID') > 0 && IPS_VariableExists($this->ReadPropertyInteger('LuxVariableID')), $this->Translate('Illuminance input is configured.'), $this->Translate('Select a readable lux variable.')),
            ModuleVisuDiagnostic::check('targets', $this->Translate('Light targets'), $this->Targets() !== [], $this->Translate('At least one light target is configured.'), $this->Translate('Add one or more target variables.'))
        ];
        return json_encode([
            'type' => 'delta',
            'state' => ModuleVisuState::cssState($mode === self::OFF ? 'inactive' : ($mode === self::MANUAL ? 'info' : 'active')),
            'stateLabel' => $label,
            'content' => $this->RenderContent($label, $checks),
            'footer' => (string)$this->GetValue('Status')
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function GetVisualizationTile(): string
    {
        $payload = json_decode($this->VisualizationPayload(), true);
        return ModuleVisuStyle::renderTile([
            'title' => $this->Translate('Dynamic Lighting'),
            'icon' => 'fa-light fa-lightbulb',
            'state' => (string)$payload['state'],
            'stateLabel' => (string)$payload['stateLabel'],
            'content' => (string)$payload['content'],
            'footer' => (string)$payload['footer']
        ]);
    }

    private function RenderContent(string $mode, array $checks): string
    {
        return ModuleVisuStyle::stateBlock($this->Translate('Mode'), $mode, 'active')
            . ModuleVisuStyle::section($this->Translate('Current values'),
                ModuleVisuStyle::stateBlock($this->Translate('Current illuminance'), number_format((float)$this->GetValue('Illuminance'), 0) . ' lx')
                . ModuleVisuStyle::stateBlock($this->Translate('Calculated brightness'), (int)$this->GetValue('CalculatedBrightness') . ' %')
                . '<div class="mvs-actions">'
                . ModuleVisuStyle::button($this->Translate('Resume ambient control'), 'ResumeAmbient', true)
                . ModuleVisuStyle::button($this->Translate('Switch off'), 'SwitchOff', true) . '</div>')
            . ModuleVisuStyle::section($this->Translate('Diagnostics'), ModuleVisuDiagnostic::renderList($checks));
    }
}
