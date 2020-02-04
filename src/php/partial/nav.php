<div class="navbar-placeholder">&nbsp;</div>
<div class="instances navbar printhide">
    <form id="instanceform" action="/change-instances" method="post">
        <div>
            <?php if (BACK): ?><a class="sidebar-backlink" href="<?= BACK ?>">Back</a></a><?php endif ?>
            <?php if (defined('BLEND_NAME')): ?>
                <?php $blends = Config::get()->blends; ?>
                <?php if (count($blends) > 1): ?>
                    <div class="navset">
                        <div class="inline-modal">
                            <div class="nav-dropdown">
                                <?php foreach (Config::get()->blends as $blendname): ?>
                                    <a href="/blend/<?= $blendname ?>" <?= $blendname == BLEND_NAME ? 'class="current"' : ''?> value="<?= $blendname ?>"><?= Blend::load($blendname)->label ?></a>
                                <?php endforeach ?>
                            </div>
                        </div>
                        <span class="inline-modal-trigger"><?= Blend::load(BLEND_NAME)->label ?></span>
                    </div>
                <?php endif ?>
                <?php
                    foreach (ContextVariableSet::getAll() as $active) {
                        $active->tinydisplay();
                        $active->display();
                    }
                ?>
            <?php endif ?>
            <?php @include BLENDS_HOME . '/src/php/partial/nav/' . PAGE . '.php'; ?>
            <input type="hidden" name="_returnurl" value="<?= htmlspecialchars_decode($_SERVER['REQUEST_URI']) ?>">
            <div id="new-vars-here" style="display: none"></div>
        </div>
    </form>
</div>
