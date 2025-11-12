<?php

use App\Services\SourceProcessors\SourceProcessorFactory;
use App\Services\SourceProcessors\UnifiedSourceProcessor;

it('creates unified processor with upload strategy', function () {
    $processor = SourceProcessorFactory::create('upload');

    expect($processor)->toBeInstanceOf(UnifiedSourceProcessor::class);
});

it('creates unified processor with url strategy', function () {
    $processor = SourceProcessorFactory::create('url');

    expect($processor)->toBeInstanceOf(UnifiedSourceProcessor::class);
});

it('creates unified processor with youtube strategy', function () {
    $processor = SourceProcessorFactory::create('youtube');

    expect($processor)->toBeInstanceOf(UnifiedSourceProcessor::class);
});

it('throws exception for unsupported source type', function () {
    expect(fn () => SourceProcessorFactory::create('unsupported'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported source type: unsupported');
});

it('validates youtube url successfully', function () {
    $result = SourceProcessorFactory::validate('youtube', 'https://www.youtube.com/watch?v=test123');

    expect($result)->toBeNull();
});

it('validates non-youtube url successfully', function () {
    $result = SourceProcessorFactory::validate('url', 'https://example.com/audio.mp3');

    expect($result)->toBeNull();
});

it('validates upload successfully', function () {
    $result = SourceProcessorFactory::validate('upload', null);

    expect($result)->toBeNull();
});

it('detects invalid youtube url', function () {
    // Test the actual YouTubeUrlValidator logic directly
    $isValid = \App\Services\YouTubeUrlValidator::isValidYouTubeUrl('invalid-url');

    expect($isValid)->toBeFalse();
});
