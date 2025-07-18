<?php

/**
 * TODO: Change, copy pasta
 * Custom form template.
 *
 * @var \Form $form
 *   The configuration form.
 * @var array $options
 *   The collection of options.
 */
?>
<?php if ($form->getTitle()) : ?>
<h1>
  <?php echo \Format::htmlchars($form->getTitle()); ?>:
  <small><?php echo \Format::htmlchars($form->getInstructions()); ?></small>
</h1>
<?php endif; ?>

<?php foreach ($form->getFields() as $field) : ?>
<div id="field<?php echo $field->getWidget()->{'id'}; ?>" class="form-field"<?php echo !$field->isVisible() ? ' style="display:none;"' : ''; ?>>

  <?php if (!$field->isBlockLevel()) : ?>
  <div class="form-field-label<?php echo $field->isRequired() ? ' required' : '' ?>">
    <?php echo \Format::htmlchars($field->getLocal('label')); ?>:

    <?php if ($field->isRequired()) : ?>
    <span class="error">*</span>
    <?php endif; ?>

    <?php if ($field->get('hint')) : ?>
    <div class="faded hint">
      <?php echo \Format::viewableImages($field->getLocal('hint')); ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="form-field-value">
  <?php endif; ?>

    <?php $field->render($options); ?>

    <?php foreach ($field->errors() as $error) : ?>
    <div class="error"><?php echo \Format::htmlchars($error); ?></div>
    <?php endforeach; ?>

  <?php if (!$field->isBlockLevel()) : ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<style>
  .custom-form-field {
    width: 338px;
  }

  .checkbox.custom-form-field {
    width: calc(350px - 1.3em);
  }

  select.custom-form-field,
  .form-field-value .redactor-box {
    width: 350px;
  }

  .form-field-value textarea {
    max-width: 340px;
    min-width: 340px;
    width: 340px;
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

  .form-field-label {
    display: inline-block;
    width: 27%;
  }

  .form-field-value {
    display: inline-block;
    max-width: 73%
  }
</style>
