<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\FormCode;
use App\Services\TemplateService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Outstanding #51 — guard against drift between the FormCode backed enum
 * and the TemplateService::FORM_TEMPLATES keys. If a new form_code is
 * added to one but not the other, this test fails.
 */
class FormCodeAllowlistTest extends TestCase
{
    #[Test]
    public function form_code_enum_values_match_template_service_keys(): void
    {
        $enumValues = FormCode::values();
        $serviceKeys = array_keys(TemplateService::FORM_TEMPLATES);

        sort($enumValues);
        sort($serviceKeys);

        $this->assertSame(
            $enumValues,
            $serviceKeys,
            'FormCode enum cases and TemplateService::FORM_TEMPLATES keys have drifted. Either is missing a form_code entry — keep them in sync.',
        );
    }

    #[Test]
    public function template_name_helper_matches_service_metadata(): void
    {
        foreach (FormCode::cases() as $case) {
            $serviceName = TemplateService::FORM_TEMPLATES[$case->value]['name'];
            $this->assertSame(
                $serviceName,
                $case->templateName(),
                "FormCode::{$case->name}->templateName() must equal TemplateService::FORM_TEMPLATES['{$case->value}']['name'].",
            );
        }
    }

    #[Test]
    public function form_code_values_are_the_canonical_three(): void
    {
        $this->assertSame(['hrp503', 'hrp503c', 'hrp398'], FormCode::values());
    }
}
