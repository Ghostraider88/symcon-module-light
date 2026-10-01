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
        $this->RegisterPropertyInteger('DiscoveryCategoryID', 0);
        $this->RegisterPropertyString('Profiles', json_encode([
            ['ProfileID' => self::PROFILE_SPRING, 'Name' => 'Frühling'],
            ['ProfileID' => self::PROFILE_SUMMER, 'Name' => 'Sommer'],
            ['ProfileID' => self::PROFILE_AUTUMN, 'Name' => 'Herbst'],
            ['ProfileID' => self::PROFILE_WINTER, 'Name' => 'Winter']
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->RegisterPropertyString('ActiveProfile', self::PROFILE_WINTER);
        $this->RegisterPropertyString('TargetProfiles', '[]');
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
        $form = json_decode(parent::GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
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
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function DiscoverLights(): void
    {
        $categoryID = $this->ReadPropertyInteger('DiscoveryCategoryID');
        if ($categoryID <= 0 || !IPS_CategoryExists($categoryID)) {
            $this->UpdateFormField('DiscoveryStatus', 'caption', $this->Translate('Choose a category to search.'));
            return;
        }

        $discovered = [];
        $luxCandidates = [];
        $this->ScanCategory($categoryID, $discovered, $luxCandidates, 0);
        $existing = $this->Targets();
        $merged = [];
        foreach ($discovered as $candidate) {
            $key = (int)($candidate['SwitchID'] ?? 0) ?: ((int)($candidate['BrightnessID'] ?? 0) ?: (int)($candidate['ColorID'] ?? 0));
            $found = null;
            foreach ($existing as $index => $target) {
                $targetKey = (int)($target['SwitchID'] ?? 0) ?: ((int)($target['BrightnessID'] ?? 0) ?: (int)($target['ColorID'] ?? 0));
                if ($key > 0 && $targetKey === $key) {
                    $found = $target;
                    unset($existing[$index]);
                    break;
                }
            }
            $merged[] = $found === null ? $candidate : array_replace($candidate, $found);
        }
        foreach ($existing as $target) {
            $merged[] = $target;
        }
        $this->UpdateFormField('Targets', 'values', json_encode($merged, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        if ($this->ReadPropertyInteger('LuxVariableID') <= 0 && count($luxCandidates) === 1) {
            $this->UpdateFormField('LuxVariableID', 'value', (string)$luxCandidates[0]);
        }
        $message = sprintf($this->Translate('Found %d light candidates and %d illuminance candidates.'), count($discovered), count($luxCandidates));
        if (count($luxCandidates) > 1) {
            $message .= ' ' . $this->Translate('Select the correct illuminance variable above.');
        }
        $this->UpdateFormField('DiscoveryStatus', 'caption', $message);
    }

    public function CaptureCurrentColor(string $targetName, string $profileID): void
    {
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
        $color = (int)GetValue($colorID);
        $colorValue = sprintf('#%06X', max(0, min(0xFFFFFF, $color)));
        $settings = $this->TargetProfiles();
        $updated = false;
        foreach ($settings as &$setting) {
            if ((string)($setting['TargetName'] ?? '') === $targetName && (string)($setting['ProfileID'] ?? '') === $profileID) {
                $setting['ColorValue'] = $colorValue;
                $updated = true;
                break;
            }
        }
        unset($setting);
        if (!$updated) {
            $settings[] = [
                'TargetName' => $targetName,
                'ProfileID' => $profileID,
                'ColorValue' => $colorValue,
                'Temperature' => 0,
                'Capture' => 'Übernehmen'
            ];
        }
        foreach ($settings as &$setting) {
            $setting['Capture'] = 'Übernehmen';
        }
        unset($setting);
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateFormField('DiscoveryStatus', 'caption', sprintf($this->Translate('Captured current color for %s / %s.'), $targetName, $this->ProfileName($profileID)));
    }

    public function DeleteProfile(string $profileID): void
    {
        $settings = array_values(array_filter(
            $this->TargetProfiles(),
            static fn (array $setting): bool => (string)($setting['ProfileID'] ?? '') !== $profileID
        ));
        foreach ($settings as &$setting) {
            $setting['Capture'] = 'Übernehmen';
        }
        unset($setting);
        $this->UpdateFormField('TargetProfiles', 'values', json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

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
        if ($this->ReadPropertyString('ActiveProfile') === $profileID) {
            $this->UpdateFormField('ActiveProfile', 'value', (string)($options[0]['value'] ?? ''));
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        if ($this->RegisterVariableString('Status', $this->Translate('Status'), '', 10)) $this->SetValue('Status', '');
        if ($this->RegisterVariableFloat('Illuminance', $this->Translate('Current illuminance'), '', 20)) $this->SetValue('Illuminance', 0.0);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Illuminance'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' lx', 'DIGITS' => 0]);
        if ($this->RegisterVariableInteger('CalculatedBrightness', $this->Translate('Calculated brightness'), '', 30)) $this->SetValue('CalculatedBrightness', 0);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('CalculatedBrightness'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %', 'MIN' => 0, 'MAX' => 100, 'DIGITS' => 0]);
        if ($this->RegisterVariableInteger('Mode', $this->Translate('Mode'), '', 40)) $this->SetValue('Mode', self::AMBIENT);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Mode'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS' => json_encode([
                ['Value' => self::AMBIENT, 'Caption' => $this->Translate('Ambient'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => self::OFF, 'Caption' => $this->Translate('Off'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => self::MANUAL, 'Caption' => $this->Translate('Manual scene'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => self::TV, 'Caption' => $this->Translate('TV scene'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1]
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
            $active = (int)GetValue($senderID);
            $requested = (int)$this->GetBuffer('RequestedScene');
            $this->SetBuffer('RequestedScene', '');
            if ($active > 0 && $active !== $requested && $active !== $this->ReadPropertyInteger('TVScene')) { $this->SetValue('Mode', self::MANUAL); $this->SetBuffer('OutputOff', '0'); }
        }
        foreach ($this->SceneTriggers() as $trigger) {
            if ((int)($trigger['VariableID'] ?? 0) === $senderID && (bool)GetValue($senderID)) {
                $this->CallScene((int)($trigger['SceneNumber'] ?? 0));
                $this->SetValue('Mode', self::MANUAL);
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
        if ($ident !== 'Mode' || !is_int($value) || !in_array($value, [self::AMBIENT, self::OFF, self::MANUAL, self::TV], true)) {
            throw new InvalidArgumentException('Invalid mode action.');
        }
        $this->SetValue('Mode', $value === self::TV ? self::MANUAL : $value);
        if ($value === self::OFF) {
            $this->TurnLightsOff();
            $this->SetValue('Status', $this->Translate('Off'));
        } elseif ($value === self::TV) {
            $this->CallScene($this->ReadPropertyInteger('TVScene'));
            $this->SetValue('Status', $this->Translate('TV scene active'));
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
        $tvID = $this->ReadPropertyInteger('TVVariableID');
        $tvOn = $tvID > 0 && IPS_VariableExists($tvID) && (bool)GetValue($tvID);
        $mode = (int)$this->GetValue('Mode');
        if ($tvOn) {
            if ($mode !== self::TV) {
                $this->CallScene($this->ReadPropertyInteger('TVScene'));
                $this->SetValue('Mode', self::TV);
            }
            $this->SetValue('Status', $this->Translate('TV scene active'));
            return;
        }
        if ($mode === self::TV) {
            if ($this->WithinSchedule() && $this->Enabled() && $lux < $this->ReadPropertyInteger('DarkLux')) {
                $this->SetValue('Mode', self::AMBIENT);
                $mode = self::AMBIENT;
            } else {
                $this->SetValue('Mode', self::AMBIENT);
                $this->TurnLightsOff();
                $this->SetValue('Status', $this->Translate('Outside schedule'));
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
        $profileID = $this->ReadPropertyString('ActiveProfile');
        foreach ($this->Targets() as $target) {
            $maximum = max(1, min(100, (int)($target['MaxBrightness'] ?? 100)));
            $minimum = max(0, min($maximum, (int)($target['MinBrightness'] ?? 0)));
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
            $settings = $this->TargetProfile($target, $profileID);
            $colorValue = $settings['ColorValue'] ?? null;
            if ($colorValue === null || $colorValue === '' || $colorValue === -1) {
                $legacySeason = $this->LegacySeasonName($profileID);
                $colorValue = $legacySeason === '' ? null : ($target[$legacySeason . 'Color'] ?? null);
            }
            $color = $this->ColorToInteger($colorValue);
            if ($id > 0 && $color !== null && IPS_VariableExists($id)) RequestAction($id, $color);
            $id = (int)($target['TemperatureID'] ?? 0);
            $temperature = (int)($settings['Temperature'] ?? 0);
            if ($temperature <= 0) {
                $legacySeason = $this->LegacySeasonName($profileID);
                $temperature = $legacySeason === '' ? 0 : (int)($target[$legacySeason . 'Temperature'] ?? 0);
            }
            if ($id > 0 && $temperature > 0 && IPS_VariableExists($id)) RequestAction($id, $temperature);
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
        return $id <= 0 || (IPS_VariableExists($id) && (bool)GetValue($id));
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
        if (($enableID > 0 && !$this->VariableHasType($enableID, [0]))
            || ($tvID > 0 && !$this->VariableHasType($tvID, [0]))) {
            return false;
        }

        $sceneControlID = $this->ReadPropertyInteger('SceneControlID');
        if ($sceneControlID > 0) {
            if (!IPS_InstanceExists($sceneControlID) || !$this->VariableHasType($this->ReadPropertyInteger('ActiveSceneID'), [1])) {
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

    private function ScanCategory(int $categoryID, array &$targets, array &$luxCandidates, int $depth): void
    {
        if ($depth > 20) {
            return;
        }
        foreach (IPS_GetChildrenIDs($categoryID) as $childID) {
            $object = IPS_GetObject($childID);
            if ($object['ObjectType'] === 0) {
                $this->ScanCategory($childID, $targets, $luxCandidates, $depth + 1);
                continue;
            }
            if ($object['ObjectType'] === 2) {
                $this->CollectIlluminanceCandidate($childID, $object, $luxCandidates);
                continue;
            }
            if ($object['ObjectType'] !== 1) {
                continue;
            }

            $candidate = [
                'Name' => $object['ObjectName'],
                'SwitchID' => 0,
                'BrightnessID' => 0,
                'BrightnessScale' => 100,
                'MaxBrightness' => 100,
                'MinBrightness' => 0,
                'ColorID' => 0,
                'TemperatureID' => 0
            ];
            foreach (IPS_GetChildrenIDs($childID) as $variableID) {
                $variableObject = IPS_GetObject($variableID);
                if ($variableObject['ObjectType'] !== 2) {
                    continue;
                }
                $variable = IPS_GetVariable($variableID);
                if ($variableObject['ObjectIsHidden']) {
                    continue;
                }
                $this->CollectIlluminanceCandidate($variableID, $variableObject, $luxCandidates);
                if ((int)($variable['VariableAction'] ?? 0) <= 0 && (int)($variable['VariableCustomAction'] ?? 0) <= 0) {
                    continue;
                }
                $profile = strtolower((string)($variable['VariableProfile'] ?? $variable['VariableCustomProfile'] ?? ''));
                $name = strtolower((string)$variableObject['ObjectName']);
                $type = (int)$variable['VariableType'];
                if ($type === 0 && (str_contains($profile, 'switch') || preg_match('/status|switch|power|ein|aus/', $name) === 1)) {
                    $candidate['SwitchID'] = $variableID;
                } elseif ($type !== 0 && (str_contains($profile, 'twcolor') || str_contains($profile, 'color_temp') || str_contains($name, 'farbtemperatur') || str_contains($name, 'kelvin'))) {
                    $candidate['TemperatureID'] = $variableID;
                } elseif ($type !== 0 && (str_contains($profile, 'hexcolor') || str_contains($name, 'farbe') || str_contains($name, 'color'))) {
                    $candidate['ColorID'] = $variableID;
                } elseif ($type !== 0 && (str_contains($profile, 'intensity') || str_contains($name, 'helligkeit') || str_contains($name, 'brightness') || str_contains($name, 'intensit'))) {
                    $candidate['BrightnessID'] = $variableID;
                    if (str_contains($profile, '.255')) {
                        $candidate['BrightnessScale'] = 255;
                    }
                }
            }
            if ($candidate['SwitchID'] > 0 || $candidate['BrightnessID'] > 0 || $candidate['ColorID'] > 0 || $candidate['TemperatureID'] > 0) {
                $targets[] = $candidate;
            }
        }
    }

    private function CollectIlluminanceCandidate(int $variableID, array $object, array &$luxCandidates): void
    {
        if ($object['ObjectIsHidden']) {
            return;
        }
        $variable = IPS_GetVariable($variableID);
        if (!in_array((int)$variable['VariableType'], [1, 2], true)) {
            return;
        }
        $name = strtolower((string)$object['ObjectName']);
        $profile = strtolower((string)($variable['VariableProfile'] ?? $variable['VariableCustomProfile'] ?? ''));
        if (str_contains($name, 'lux') || str_contains($name, 'illuminance') || str_contains($name, 'beleuchtungsstärke') || str_contains($profile, 'illuminance') || (str_contains($name, 'helligkeit') && !str_contains($profile, 'intensity'))) {
            if (!in_array($variableID, $luxCandidates, true)) {
                $luxCandidates[] = $variableID;
            }
        }
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
        $rows = json_decode($this->ReadPropertyString('TargetProfiles'), true);
        return is_array($rows) ? $rows : [];
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
            return max(0, min(0xFFFFFF, (int)$value));
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = ltrim(trim($value), '#');
        if (str_starts_with(strtolower($value), '0x')) {
            $value = substr($value, 2);
        }
        return ctype_xdigit($value) ? (int)hexdec($value) : null;
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
        return is_array($rows) ? $rows : [];
    }

    private function VisualizationPayload(): string
    {
        $mode = (int)$this->GetValue('Mode');
        $label = match ($mode) {
            self::OFF => $this->Translate('Off'),
            self::MANUAL => $this->Translate('Manual scene'),
            self::TV => $this->Translate('TV scene'),
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
