<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/VisuStyle.php';
require_once __DIR__ . '/../libs/VisuState.php';
require_once __DIR__ . '/../libs/VisuDiagnostic.php';
require_once __DIR__ . '/../libs/VisuCapability.php';

/**
 * MyModule
 *
 * Das Beispiel zeigt den verbindlichen Visu-Vertrag des Modul-Templates:
 * Zustandsmodell, Diagnose-Checkliste, Capability-Prüfung und Live-Update.
 */
class MyModule extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->SetVisualizationType(1);

        $this->RegisterPropertyString('Hostname', '');
        $this->RegisterPropertyInteger('Interval', 0); // Sekunden; 0 = Timer aus
        $this->RegisterAttributeString('LastResponse', '');

        $this->RegisterTimer('UpdateTimer', 0, 'MYM_Update($_IPS['TARGET']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if ($this->RegisterVariableString('Status', $this->Translate('Status'), '', 10)) {
            $this->SetValue('Status', '');
        }

        if ($this->RegisterVariableBoolean(
            'Switch',
            $this->Translate('Switch'),
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            20
        )) {
            $this->SetValue('Switch', false);
        }
        $this->MaintainAction('Switch', true);
        $this->RegisterMessage($this->GetIDForIdent('Switch'), VM_UPDATE);

        $interval = $this->ReadPropertyInteger('Interval');
        if ($interval < 0) {
            $this->SetTimerInterval('UpdateTimer', 0);
            $this->SetStatus(202);
            return;
        }
        $this->SetTimerInterval('UpdateTimer', $interval * 1000);

        $this->SetStatus($this->ReadPropertyString('Hostname') === '' ? 104 : 102);
    }

    public function MessageSink(int $timestamp, int $senderID, int $message, array $data): void
    {
        if ($message === VM_UPDATE) {
            $this->UpdateVisualizationValue($this->GetVisualizationPayload());
        }
    }

    public function RequestAction(string $ident, mixed $value): void
    {
        switch ($ident) {
            case 'Switch':
                if (!is_bool($value)) {
                    throw new InvalidArgumentException('Switch expects a boolean value.');
                }
                $this->SetValue('Switch', $value);
                // In einem echten Modul hier den Gerätebefehl senden.
                $this->UpdateVisualizationValue($this->GetVisualizationPayload());
                break;

            case 'RunSelfTest':
                // Diagnose bleibt read-only: Es werden keine Reparaturbefehle ausgeführt.
                $this->UpdateVisualizationValue($this->GetVisualizationPayload());
                break;

            default:
                throw new Exception('Invalid Ident: ' . $ident);
        }
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderVisualization();
    }

    private function GetVisualizationPayload(): string
    {
        $isOn = (bool)$this->GetValue('Switch');
        $state = $isOn ? 'active' : 'inactive';
        $checks = $this->GetDiagnosticChecks();

        return json_encode([
            'type'       => 'delta',
            'state'      => ModuleVisuState::cssState($state),
            'stateLabel' => $isOn ? $this->Translate('Active') : $this->Translate('Inactive'),
            'content'    => $this->RenderVisualizationContent($isOn, $checks),
            'footer'     => date('d.m.Y H:i:s'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function RenderVisualization(): string
    {
        $isOn = (bool)$this->GetValue('Switch');
        $state = $isOn ? 'active' : 'inactive';
        $checks = $this->GetDiagnosticChecks();

        return ModuleVisuStyle::renderTile([
            'title'      => $this->Translate('My Module'),
            'icon'       => 'fa-light fa-cube',
            'state'      => ModuleVisuState::cssState($state),
            'stateLabel' => $isOn ? $this->Translate('Active') : $this->Translate('Inactive'),
            'content'    => $this->RenderVisualizationContent($isOn, $checks),
            'footer'     => date('d.m.Y H:i:s'),
        ]);
    }

    private function RenderVisualizationContent(bool $isOn, array $checks): string
    {
        $state = $isOn ? 'active' : 'inactive';
        $capabilities = ['switch'];
        $content = ModuleVisuStyle::stateBlock(
            $this->Translate('Current state'),
            $isOn ? $this->Translate('Switched on') : $this->Translate('Switched off'),
            $state
        );

        $content .= ModuleVisuCapability::when(
            $capabilities,
            'switch',
            fn(): string => ModuleVisuStyle::section(
                $this->Translate('Actions'),
                '<div class="mvs-actions">'
                . ModuleVisuStyle::button(
                    $isOn ? $this->Translate('Switch off') : $this->Translate('Switch on'),
                    'Switch',
                    !$isOn
                )
                . '</div>'
            )
        );

        $content .= ModuleVisuStyle::section(
            $this->Translate('Diagnostics'),
            ModuleVisuStyle::button($this->Translate('Run self-test'), 'RunSelfTest', true, true)
            . '<div class="mvs-content" style="margin-top:8px">'
            . ModuleVisuDiagnostic::renderList($checks)
            . '</div>'
        );

        return $content;
    }

    private function GetDiagnosticChecks(): array
    {
        $hostname = trim($this->ReadPropertyString('Hostname'));

        return [
            ModuleVisuDiagnostic::check(
                'configuration',
                $this->Translate('Configuration'),
                $hostname !== '',
                $hostname === ''
                    ? $this->Translate('Hostname is not configured.')
                    : $this->Translate('Configuration is complete.'),
                $hostname === '' ? $this->Translate('Enter a hostname in the configuration.') : '',
                'not_configured'
            ),
            ModuleVisuDiagnostic::check(
                'switch',
                $this->Translate('Switch capability'),
                ModuleVisuCapability::has(['switch'], 'switch'),
                $this->Translate('Switch control is available.')
            ),
        ];
    }

    public function Update(): void
    {
        $host = $this->ReadPropertyString('Hostname');
        if ($host === '') {
            $this->SetStatus(104);
            $this->UpdateVisualizationValue($this->GetVisualizationPayload());
            return;
        }

        $this->SendDebug(__FUNCTION__, 'Updating from ' . $host, 0);
        $result = 'OK @ ' . date('H:i:s');

        $this->WriteAttributeString('LastResponse', $result);
        $this->SetValue('Status', $result);
        $this->SetStatus(102);
        $this->UpdateVisualizationValue($this->GetVisualizationPayload());
    }

    public function HelloWorld(): string
    {
        return $this->Translate('Hello World');
    }
}
