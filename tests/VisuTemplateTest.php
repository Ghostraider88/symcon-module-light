<?php

declare(strict_types=1);

use PHPUnitFrameworkTestCase;

require_once __DIR__ . '/libs/VisuState.php';
require_once __DIR__ . '/libs/VisuDiagnostic.php';
require_once __DIR__ . '/libs/VisuCapability.php';

final class VisuTemplateTest extends TestCase
{
    public function testStateNormalization(): void
    {
        $this->assertSame('warning', ModuleVisuState::normalize('warning'));
        $this->assertSame('normal', ModuleVisuState::cssState('ok'));
        $this->assertSame('warning', ModuleVisuState::cssState('stale'));
        $this->assertTrue(ModuleVisuState::isProblem('offline'));
        $this->assertFalse(ModuleVisuState::isAvailable('not_configured'));
    }

    public function testDiagnosticCheckAndRendering(): void
    {
        $checks = [
            ModuleVisuDiagnostic::check('ok', 'Verbindung', true, 'Verbindung vorhanden.'),
            ModuleVisuDiagnostic::check('bad', 'Token', false, 'Token fehlt.', 'Token konfigurieren.'),
        ];

        $this->assertTrue(ModuleVisuDiagnostic::hasProblems($checks));
        $this->assertStringContainsString('Verbindung', ModuleVisuDiagnostic::renderList($checks));
        $this->assertStringContainsString('Token konfigurieren.', ModuleVisuDiagnostic::renderList($checks));
    }

    public function testCapabilityRendering(): void
    {
        $this->assertTrue(ModuleVisuCapability::has(['switch'], 'switch'));
        $this->assertSame('', ModuleVisuCapability::when([], 'switch', static fn(): string => 'hidden'));
        $this->assertSame('visible', ModuleVisuCapability::when(['switch'], 'switch', static fn(): string => 'visible'));
    }
}
