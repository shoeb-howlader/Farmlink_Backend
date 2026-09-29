<?php

use App\Services\RichTextSanitizer;

test('sanitizer converts h1 tags to h2 for single-h1 SEO structure', function () {
    $input = '<h1>Main Product Header</h1><p>Product description</p>';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->not->toContain('<h1>');
    expect($cleaned)->not->toContain('</h1>');
    expect($cleaned)->toContain('<h2>Main Product Header</h2>');
});

test('sanitizer preserves h2 and h3 headings but strips unapproved heading levels', function () {
    $input = '<h2>Section Header</h2><h3>Sub Header</h3><h4>Too Small</h4><h5>Tiny</h5><h6>Micro</h6>';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->toContain('<h2>Section Header</h2>');
    expect($cleaned)->toContain('<h3>Sub Header</h3>');
    expect($cleaned)->not->toContain('<h4>');
    expect($cleaned)->not->toContain('<h5>');
    expect($cleaned)->not->toContain('<h6>');
});

test('sanitizer strips script and iframe tags completely along with their inner content', function () {
    $input = '<div>
        <p>Legitimate text</p>
        <script>alert("malicious script execution");</script>
        <iframe src="https://evil.example.com/steal-session"></iframe>
        <style>body { display: none; }</style>
    </div>';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->not->toContain('<script');
    expect($cleaned)->not->toContain('alert("malicious');
    expect($cleaned)->not->toContain('<iframe');
    expect($cleaned)->not->toContain('evil.example.com');
    expect($cleaned)->not->toContain('<style');
    expect($cleaned)->toContain('<p>Legitimate text</p>');
});

test('sanitizer strips inline style attributes and event handler attributes', function () {
    $input = '<p style="color: red; font-size: 200px;" onclick="doHarm()" onmouseover="trackUser()">Clean text</p>
              <img src="https://farmlink.test/photo.jpg" onerror="stealCredentials()" style="border: 5px solid red;" alt="Safe photo">';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->not->toContain('style=');
    expect($cleaned)->not->toContain('color: red');
    expect($cleaned)->not->toContain('onclick');
    expect($cleaned)->not->toContain('onmouseover');
    expect($cleaned)->not->toContain('onerror');
    expect($cleaned)->toContain('<p>Clean text</p>');
    expect($cleaned)->toContain('<img src="https://farmlink.test/photo.jpg" alt="Safe photo">');
});

test('sanitizer rejects base64 data:image URIs and dangerous javascript schemes', function () {
    $input = '<p>Pasted base64 image:</p>
              <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==" alt="Base64 Test">
              <a href="javascript:alert(1)">Click Me</a>
              <a href="https://farmlink.test/product-guide">Legit Link</a>';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->not->toContain('data:image');
    expect($cleaned)->not->toContain('javascript:');
    expect($cleaned)->not->toContain('<img'); // img without valid non-data src should be removed
    expect($cleaned)->toContain('<a href="https://farmlink.test/product-guide">Legit Link</a>');
});

test('sanitizer unwraps pre and code blocks', function () {
    $input = '<p>Here is code:</p><pre><code>console.log("hello");</code></pre>';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->not->toContain('<pre>');
    expect($cleaned)->not->toContain('<code>');
    expect($cleaned)->toContain('console.log("hello");');
});

test('sanitizer preserves valid tables, lists, blockquotes, and formatting', function () {
    $input = '<blockquote>Notice for farmers</blockquote>
              <ul><li>Item 1</li><li>Item 2</li></ul>
              <ol><li>Step 1</li></ol>
              <p><strong>Bold</strong> and <em>Italic</em> and <s>Strikethrough</s></p>
              <table><thead><tr><th>Dosage</th></tr></thead><tbody><tr><td>100g</td></tr></tbody></table>';
    $cleaned = RichTextSanitizer::sanitize($input);

    expect($cleaned)->toContain('<blockquote>Notice for farmers</blockquote>');
    expect($cleaned)->toContain('<ul><li>Item 1</li><li>Item 2</li></ul>');
    expect($cleaned)->toContain('<ol><li>Step 1</li></ol>');
    expect($cleaned)->toContain('<strong>Bold</strong>');
    expect($cleaned)->toContain('<em>Italic</em>');
    expect($cleaned)->toContain('<s>Strikethrough</s>');
    expect($cleaned)->toContain('<table><thead><tr><th>Dosage</th></tr></thead><tbody><tr><td>100g</td></tr></tbody></table>');
});
