<?php
/**
 * TicketMind Signals Plugin — TicketMind API Client
 * Copyright (C) 2025  Solve42 GmbH
 * Author: Eugen Massini <info@solve42.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-only
 */
/**
 * @var \Form $form config form
 * @var array $options options
 */
?>

<?php
$title = $form->getTitle();
if ($title) : ?>
    <h1>
        <?= \Format::htmlchars($title); ?>:
        <small><?= \Format::htmlchars($form->getInstructions()); ?></small>
    </h1>
<?php endif; ?>

<?php foreach ($form->getFields() as $field) : ?>
    <?php
    $widget      = $field->getWidget();
    $wid         = $widget->{'id'};
    $hiddenStyle = $field->isVisible() ? '' : ' style="display:none;"';
    $labelClass  = 'form-field-label' . ($field->isRequired() ? ' required' : '');
    $hasHint     = (bool) $field->get('hint');
    ?>

    <div id="field<?= $wid; ?>" class="form-field"<?= $hiddenStyle; ?>>


        <?php if (!$field->isBlockLevel()) : ?>
        <div class="<?= $labelClass; ?>">
            <?= \Format::htmlchars($field->getLocal('label')); ?>:
            <?php if ($field->isRequired()) : ?>
                <span class="error">*</span>
            <?php endif; ?>

            <?php if ($hasHint) : ?>
                <div class="faded hint">
                    <?= \Format::viewableImages($field->getLocal('hint')); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-field-value">
            <?php endif; ?>


            <?php $field->render($options); ?>

            <?php foreach ($field->errors() as $error) : ?>
                <div class="error"><?= \Format::htmlchars($error); ?></div>
            <?php endforeach; ?>

            <?php if (!$field->isBlockLevel()) : ?>
        </div>
    <?php endif; ?>

    </div>
<?php endforeach; ?>

<style>
  select.form-field-value {
    width: 400px;
  }

  .form-field-value textarea {
    max-width: 390px;
    min-width: 390px;
    width: 390px;
  }

  .form-field div {
    vertical-align: top;
  }

  .form-field div + div {
    padding-left: 10px;
  }

  .form-field .hint {
    font-size: 95%;
  }

  .form-field {
    margin-top: 5px;
    padding: 5px 0;
  }

  .form-field-value {
    display: inline-block;
    max-width: 75%
  }
</style>
