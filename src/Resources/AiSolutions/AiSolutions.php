<?php

namespace FLAIRUK\Uber\Resources\AiSolutions;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Uber AI Solutions (scaled solutions): data-labelling batches and machine translation.
 * App token, scope scaledsolutions.batch.
 *
 * @see https://developer.uber.com/docs/scaled-solutions/introduction
 */
class AiSolutions extends Resource
{
    protected array $scopes = ['scaledsolutions.batch'];

    /**
     * Submit a labelling, annotation, transcription or translation batch.
     *
     * @param  array<string, mixed>  $batch  ['project_id', 'domain', 'batch_data', 'name'?, 'priority'?, 'delivery_options'?]
     */
    public function submitBatch(array $batch): Response
    {
        return $this->post('v1/scaledsolutions/batch', $batch);
    }

    public function batch(string $batchId): Response
    {
        return $this->get('v1/scaledsolutions/batch/'.$this->segment($batchId));
    }

    public function batches(string $projectId, ?int $limit = null, ?string $pageToken = null): Response
    {
        return $this->send('POST', 'v1/scaledsolutions/project/'.$this->segment($projectId).'/batches', [
            'query' => ['limit' => $limit, 'page_token' => $pageToken],
        ], retry: true);
    }

    public function cancelBatch(string $batchId): Response
    {
        return $this->post('v1/scaledsolutions/batch/'.$this->segment($batchId).'/cancel');
    }

    public function tasks(string $batchId, ?int $limit = null, ?string $pageToken = null): Response
    {
        return $this->get('v1/scaledsolutions/batch/'.$this->segment($batchId).'/tasks', ['limit' => $limit, 'page_token' => $pageToken]);
    }

    /**
     * Prepare the results file; then fetch it with result().
     *
     * @param  array<string, mixed>  $filters
     */
    public function generateResult(string $batchId, bool $allowPartial = false, array $filters = []): Response
    {
        return $this->post('v1/scaledsolutions/batch/'.$this->segment($batchId).'/result/generate', [
            'allow_partial_result_generation' => $allowPartial,
            'filters' => $filters ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query  ['allow_partial_result', 'filters.completion_time_range.start_time', ...]
     */
    public function result(string $batchId, array $query = []): Response
    {
        return $this->get('v1/scaledsolutions/batch/'.$this->segment($batchId).'/result', $query);
    }

    /**
     * Machine-translate one text, e.g. translate('en_US', 'fr_FR', 'Hello').
     *
     * @param  array<string, mixed>  $options
     */
    public function translate(string $sourceLocale, string $targetLocale, string $text, array $options = []): Response
    {
        return $this->post('v1/scaledsolutions/localization/mt/translate', [
            'source_locale' => $sourceLocale,
            'target_locale' => $targetLocale,
            'text' => $text,
            'options' => $options ?: null,
        ], retry: true);
    }

    /**
     * @param  list<mixed>  $items
     * @param  array<string, mixed>  $options
     */
    public function translateBatch(string $sourceLocale, string $targetLocale, array $items, array $options = []): Response
    {
        return $this->post('v1/scaledsolutions/localization/mt/translate/batch', [
            'source_locale' => $sourceLocale,
            'target_locale' => $targetLocale,
            'items' => $items,
            'options' => $options ?: null,
        ], retry: true);
    }

    /**
     * Translate with a generative model.
     *
     * @param  array<string, mixed>  $options
     */
    public function translateWithGenAi(string $sourceLocale, string $targetLocale, string $text, array $options = []): Response
    {
        return $this->post('v1/scaledsolutions/localization/genai/translate', [
            'source_locale' => $sourceLocale,
            'target_locale' => $targetLocale,
            'text' => $text,
            'options' => $options ?: null,
        ], retry: true);
    }
}
