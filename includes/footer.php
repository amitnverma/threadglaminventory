    </main>
</div>
<script src="assets/js/app.js"></script>
<?php if (!empty($loadInventorySheet)): ?>
<script src="assets/js/inventory-sheet.js"></script>
<?php endif; ?>
<?php if (!empty($loadDecorInventory)): ?>
<script src="assets/js/decor-inventory.js"></script>
<?php endif; ?>
<?php if (!empty($loadDecorProposals)): ?>
<script src="assets/js/decor-proposals.js"></script>
<?php endif; ?>
<?php if (!empty($pageScripts)): ?>
<?php foreach ((array)$pageScripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
