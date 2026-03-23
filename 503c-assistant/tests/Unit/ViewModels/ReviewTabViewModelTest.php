<?php

namespace Tests\Unit\ViewModels;

use App\Models\DocumentChunk;
use App\Models\ProjectDocument;
use App\Models\FieldDefinition;
use App\Models\FieldEvidence;
use App\Models\Project;
use App\Models\ProjectFieldValue;
use App\ViewModels\Projects\ReviewTabViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTabViewModelTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private FieldDefinition $field1;

    private FieldDefinition $field2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();

        $this->field1 = FieldDefinition::factory()->create([
            'key' => 'research_type',
            'label' => 'Research Type',
            'sort_order' => 1,
        ]);

        $this->field2 = FieldDefinition::factory()->create([
            'key' => 'involves_human_subjects',
            'label' => 'Involves Human Subjects',
            'sort_order' => 2,
        ]);
    }

    public function test_get_field_list_data_returns_correct_structure()
    {
        // Arrange
        $fv1 = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'status' => 'suggested',
        ]);

        $fv2 = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field2->id,
            'status' => 'confirmed',
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv1);

        // Act
        $result = $viewModel->getFieldListData(collect([$fv1, $fv2]));

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fieldValues', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertCount(2, $result['fieldValues']);
    }

    public function test_get_field_list_data_calculates_stats_correctly()
    {
        // Arrange
        $fv1 = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'status' => 'confirmed',
        ]);

        $fv2 = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field2->id,
            'status' => 'suggested',
        ]);

        $viewModel = new ReviewTabViewModel($this->project);

        // Act
        $result = $viewModel->getFieldListData(collect([$fv1, $fv2]));

        // Assert
        $this->assertEquals(2, $result['stats']['total']);
        $this->assertEquals(1, $result['stats']['confirmed']);
        $this->assertEquals(1, $result['stats']['suggested']);
    }

    public function test_get_field_list_data_handles_empty_collection()
    {
        // Arrange
        $viewModel = new ReviewTabViewModel($this->project);

        // Act
        $result = $viewModel->getFieldListData(collect());

        // Assert
        $this->assertCount(0, $result['fieldValues']);
        $this->assertEquals(0, $result['stats']['total']);
        $this->assertEquals(0, $result['stats']['confirmed']);
    }

    public function test_get_field_list_data_prepares_search_text()
    {
        // Arrange
        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'status' => 'suggested',
        ]);

        $viewModel = new ReviewTabViewModel($this->project);

        // Act
        $result = $viewModel->getFieldListData(collect([$fv]));
        $fieldValueData = $result['fieldValues']->first();

        // Assert
        $this->assertEquals('research_type research type', $fieldValueData['search_text']);
    }

    public function test_get_field_list_data_determines_badge_class()
    {
        // Arrange
        $fvConfirmed = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'status' => 'confirmed',
        ]);

        $fvEdited = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field2->id,
            'status' => 'edited',
        ]);

        $viewModel = new ReviewTabViewModel($this->project);

        // Act
        $result = $viewModel->getFieldListData(collect([$fvConfirmed, $fvEdited]));

        // Assert
        $confirmedBadge = $result['fieldValues']->first()['badge_class'];
        $editedBadge = $result['fieldValues']->last()['badge_class'];

        $this->assertStringContainsString('bg-green-50', $confirmedBadge);
        $this->assertStringContainsString('bg-amber-50', $editedBadge);
    }

    public function test_get_field_editor_data_with_suggested_value()
    {
        // Arrange
        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'suggested_value' => 'Observational study',
            'final_value' => null,
            'status' => 'suggested',
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getFieldEditorData();

        // Assert
        $this->assertEquals('Research Type', $result['label']);
        $this->assertEquals('research_type', $result['key']);
        $this->assertEquals('Observational study', $result['suggested_value']);
        $this->assertTrue($result['show_suggested']);
        $this->assertFalse($result['show_final']);
        $this->assertEquals('SUGGESTED', $result['status']);
    }

    public function test_get_field_editor_data_detects_edited_state()
    {
        // Arrange
        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'suggested_value' => 'Observational study',
            'final_value' => 'Interventional study',
            'status' => 'edited',
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getFieldEditorData();

        // Assert
        $this->assertTrue($result['is_edited']);
        $this->assertTrue($result['show_suggested']);
        $this->assertTrue($result['show_final']);
    }

    public function test_get_field_editor_data_handles_null_selected()
    {
        // Arrange
        $viewModel = new ReviewTabViewModel($this->project, null);

        // Act
        $result = $viewModel->getFieldEditorData();

        // Assert
        $this->assertEquals('', $result['label']);
        $this->assertEquals('', $result['key']);
        $this->assertFalse($result['show_suggested']);
        $this->assertFalse($result['show_final']);
    }

    public function test_get_evidence_viewer_data_with_evidence()
    {
        // Arrange
        $document = ProjectDocument::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $chunk = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
            'text' => 'This study involves minimal risk to participants.',
            'page_number' => 5,
            'chunk_index' => 2,
        ]);

        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        $evidence = FieldEvidence::factory()->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk->id,
            'excerpt_text' => 'minimal risk',
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getEvidenceViewerData();

        // Assert
        $this->assertTrue($result['has_evidence']);
        $this->assertCount(1, $result['evidence']);
        $this->assertNotNull($result['active_evidence']);
        $this->assertNotNull($result['active_chunk']);
        $this->assertNotNull($result['active_document']);
    }

    public function test_get_evidence_viewer_data_with_active_parameter()
    {
        // Arrange
        $document = ProjectDocument::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $chunk1 = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
            'text' => 'First chunk',
        ]);

        $chunk2 = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
            'text' => 'Second chunk',
        ]);

        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        $evidence1 = FieldEvidence::factory()->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk1->id,
        ]);

        $evidence2 = FieldEvidence::factory()->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk2->id,
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act - request second evidence as active
        $result = $viewModel->getEvidenceViewerData($evidence2->id);

        // Assert
        $this->assertEquals($evidence2->id, $result['active_evidence']->id);
        $this->assertEquals('Second chunk', $result['active_chunk']->text);
    }

    public function test_get_evidence_viewer_data_defaults_to_first_evidence()
    {
        // Arrange
        $document = ProjectDocument::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $chunk = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
        ]);

        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        $evidence = FieldEvidence::factory()->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk->id,
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act - no active evidence ID provided
        $result = $viewModel->getEvidenceViewerData();

        // Assert
        $this->assertEquals($evidence->id, $result['active_evidence']->id);
    }

    public function test_get_evidence_viewer_data_handles_no_evidence()
    {
        // Arrange
        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getEvidenceViewerData();

        // Assert
        $this->assertFalse($result['has_evidence']);
        $this->assertCount(0, $result['evidence']);
        $this->assertNull($result['active_evidence']);
    }

    public function test_get_evidence_viewer_data_handles_null_selected()
    {
        // Arrange
        $viewModel = new ReviewTabViewModel($this->project, null);

        // Act
        $result = $viewModel->getEvidenceViewerData();

        // Assert
        $this->assertFalse($result['has_evidence']);
        $this->assertNull($result['active_evidence']);
    }

    public function test_highlight_chunk_adds_mark_tags()
    {
        // Arrange
        $document = ProjectDocument::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $chunk = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
            'text' => 'This study involves minimal risk to participants.',
        ]);

        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        FieldEvidence::factory()->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk->id,
            'excerpt_text' => 'minimal risk',
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getEvidenceViewerData();

        // Assert
        $this->assertStringContainsString('<mark class="bg-yellow-100">', $result['highlighted_chunk']);
        $this->assertStringContainsString('minimal risk', $result['highlighted_chunk']);
    }

    public function test_get_evidence_viewer_data_detects_quote_mismatch()
    {
        // Arrange
        $document = ProjectDocument::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $chunk = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
            'text' => 'This text does not contain the quote.',
        ]);

        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        FieldEvidence::factory()->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk->id,
            'excerpt_text' => 'participants must be 18 years or older',
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getEvidenceViewerData();

        // Assert
        $this->assertTrue($result['quote_mismatch']);
    }

    public function test_get_field_list_data_determines_selection_state()
    {
        // Arrange
        $fv1 = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        $fv2 = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field2->id,
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv1);

        // Act
        $result = $viewModel->getFieldListData(collect([$fv1, $fv2]));
        $fieldValues = $result['fieldValues'];

        // Assert
        $this->assertTrue($fieldValues->first()['is_selected']);
        $this->assertFalse($fieldValues->last()['is_selected']);
    }

    public function test_get_field_editor_data_includes_evidence_count()
    {
        // Arrange
        $document = ProjectDocument::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $chunk = DocumentChunk::factory()->create([
            'project_document_id' => $document->id,
        ]);

        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
        ]);

        FieldEvidence::factory()->count(3)->create([
            'project_field_value_id' => $fv->id,
            'document_chunk_id' => $chunk->id,
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getFieldEditorData();

        // Assert
        $this->assertEquals(3, $result['evidence_count']);
    }

    public function test_get_field_editor_data_formats_confidence_score()
    {
        // Arrange
        $fv = ProjectFieldValue::factory()->create([
            'project_id' => $this->project->id,
            'field_definition_id' => $this->field1->id,
            'confidence' => 0.9567,
        ]);

        $viewModel = new ReviewTabViewModel($this->project, $fv);

        // Act
        $result = $viewModel->getFieldEditorData();

        // Assert
        $this->assertEquals('0.96', $result['confidence']);
    }
}
