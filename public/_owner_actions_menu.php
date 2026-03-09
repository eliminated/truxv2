<?php
declare(strict_types=1);

$entityType = isset($entityType) ? (string)$entityType : 'post';
$entityId = isset($entityId) ? (int)$entityId : 0;
?>
<div class="contentMenu" data-content-menu="1">
  <button
    class="contentMenu__trigger"
    type="button"
    aria-label="Open <?= trux_e($entityType) ?> actions"
    data-content-menu-trigger="1">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M6.5 12a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Z" fill="currentColor" />
    </svg>
  </button>

  <div class="contentMenu__panel" role="menu" aria-label="<?= trux_e(ucfirst($entityType)) ?> actions">
    <button
      class="contentMenu__item"
      type="button"
      role="menuitem"
      data-owner-edit="1"
      data-owner-type="<?= trux_e($entityType) ?>"
      data-owner-id="<?= $entityId ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4 20h4.2l9.8-9.8-4.2-4.2L4 15.8V20Zm11.1-13.9 4.2 4.2 1.4-1.4a1.5 1.5 0 0 0 0-2.1l-2.1-2.1a1.5 1.5 0 0 0-2.1 0l-1.4 1.4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
      </svg>
      <span>Edit</span>
    </button>

    <button
      class="contentMenu__item"
      type="button"
      role="menuitem"
      data-owner-bookmark="1"
      data-owner-type="<?= trux_e($entityType) ?>"
      data-owner-id="<?= $entityId ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="m7 5 5-2 5 2v14l-5-2-5 2V5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
      </svg>
      <span data-owner-bookmark-label="1">Bookmark</span>
    </button>

    <button
      class="contentMenu__item contentMenu__item--danger"
      type="button"
      role="menuitem"
      data-owner-delete="1"
      data-owner-type="<?= trux_e($entityType) ?>"
      data-owner-id="<?= $entityId ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4.75 7.5h14.5M9.5 7.5V5.75a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7.5M7.5 7.5l.9 11.2a2 2 0 0 0 2 1.8h3.2a2 2 0 0 0 2-1.8l.9-11.2M10.25 11v6.25M13.75 11v6.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Delete</span>
    </button>
  </div>
</div>
