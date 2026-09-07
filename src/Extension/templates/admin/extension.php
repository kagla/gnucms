<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><?php $this->stop() ?>
<?php $this->start('body') ?>
<?= $this->block('extension_body') ?>
<?php $this->stop() ?>
