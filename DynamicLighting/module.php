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
        $season = ucfirst($this->ReadPropertyString('Season'));
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
            $color = (int)($target[$season . 'Color'] ?? 0);
            if ($id > 0 && $color > 0 && IPS_VariableExists($id)) RequestAction($id, $color);
            $id = (int)($target['TemperatureID'] ?? 0);
            $temperature = (int)($target[$season . 'Temperature'] ?? 0);
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
            || !in_array($this->ReadPropertyString('Season'), ['spring', 'summer', 'autumn', 'winter'], true)
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
