<?php
require __DIR__ . '/../header.php';
if (!isAdmin()) {
    http_response_code(403);
    echo "<h6>Access denied</h6>";
    require __DIR__.'/../footer.php';
    exit;
}

$stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1 LIMIT 1");
$settings = $stmt->fetch() ?: [];

$errors = [];
$success = "";

// EDIT MODE
$isEdit = isset($_GET['edit']) && $_GET['edit'] == 1;

// Validation
function validateGST($v){ return preg_match("/^[0-9]{2}[A-Z0-9]{13}$/",$v); }
function validatePAN($v){ return preg_match("/^[A-Z]{5}[0-9]{4}[A-Z]$/",$v); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Logo upload
    $logo_path = $settings['logo_path'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $upload_dir = __DIR__ . "/../uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            $errors[] = "Logo must be JPG/PNG.";
        } else {
            $file = "logo_" . time() . "." . $ext;
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir.$file)) {
                $errors[] = "Logo upload failed.";
            } else {
                $logo_path = "uploads/" . $file;
            }
        }
    }

    $data = [
        'company_name'=>trim($_POST['company_name']), 'legal_name'=>trim($_POST['legal_name']),
        'phone'=>trim($_POST['phone']), 'email'=>trim($_POST['email']), 'website'=>trim($_POST['website']),
        'gst_number'=>trim($_POST['gst_number']), 'pan_number'=>trim($_POST['pan_number']),
        'cin_number'=>trim($_POST['cin_number']), 'address'=>trim($_POST['address']),
        'city'=>trim($_POST['city']), 'state'=>trim($_POST['state']),
        'pincode'=>trim($_POST['pincode']), 'country'=>trim($_POST['country']),
        'invoice_prefix'=>trim($_POST['invoice_prefix']),
        'next_invoice_no'=>(int)$_POST['next_invoice_no'],
        'tax_type'=>$_POST['tax_type'], 'tax_percent'=>(float)$_POST['tax_percent'],
        'show_delivery'=>isset($_POST['show_delivery'])?1:0,
        'bank_name'=>trim($_POST['bank_name']), 'bank_holder'=>trim($_POST['bank_holder']),
        'bank_account'=>trim($_POST['bank_account']), 'ifsc_code'=>trim($_POST['ifsc_code']),
        'branch'=>trim($_POST['branch']), 'upi_id'=>trim($_POST['upi_id']),
        'invoice_terms'=>trim($_POST['invoice_terms']), 'footer_notes'=>trim($_POST['footer_notes']),
        'logo_path'=>$logo_path
    ];

    if ($data['company_name'] === "") $errors[] = "Company name required.";
    if ($data['gst_number'] && !validateGST($data['gst_number'])) $errors[] = "Invalid GST.";
    if ($data['pan_number'] && !validatePAN($data['pan_number'])) $errors[] = "Invalid PAN.";

    if (!$errors) {
        $stmt=$pdo->prepare("UPDATE company_settings SET
            company_name=:company_name,legal_name=:legal_name,phone=:phone,email=:email,website=:website,
            gst_number=:gst_number,pan_number=:pan_number,cin_number=:cin_number,
            address=:address,city=:city,state=:state,pincode=:pincode,country=:country,
            invoice_prefix=:invoice_prefix,next_invoice_no=:next_invoice_no,
            tax_type=:tax_type,tax_percent=:tax_percent,show_delivery=:show_delivery,
            bank_name=:bank_name,bank_holder=:bank_holder,bank_account=:bank_account,
            ifsc_code=:ifsc_code,branch=:branch,upi_id=:upi_id,
            invoice_terms=:invoice_terms,footer_notes=:footer_notes,
            logo_path=:logo_path
        WHERE id=1");
        $stmt->execute($data);
        header("Location: index.php?page=settings");
        exit;
    }
}
?>

<div class="card shadow-sm border-0">
<div class="card-body" style="font-size:14px;">

<?php if($errors): ?>
<div class="alert alert-danger py-2 small"><ul class="m-0">
<?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="settingsForm">

<!-- BUSINESS CARD -->
<div class="card border-0 shadow-sm mb-3">
<div class="card-header py-2 small fw-bold">Business Information</div>
<div class="card-body">
<div class="row g-3">

    <div class="col-md-3">
        <label class="small">Company Name*</label>
        <input type="text" name="company_name"
            value="<?=$settings['company_name']?>"
            class="form-control form-control-sm"
            <?= !$isEdit ? 'readonly' : '' ?> required>
    </div>

    <div class="col-md-3">
        <label class="small">Legal Name</label>
        <input type="text" name="legal_name" value="<?=$settings['legal_name']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">Phone</label>
        <input type="text" name="phone" value="<?=$settings['phone']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">Email</label>
        <input type="email" name="email" value="<?=$settings['email']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">Website</label>
        <input type="text" name="website" value="<?=$settings['website']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">GST</label>
        <input type="text" name="gst_number" value="<?=$settings['gst_number']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">PAN</label>
        <input type="text" name="pan_number" value="<?=$settings['pan_number']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">CIN</label>
        <input type="text" name="cin_number" value="<?=$settings['cin_number']?>"
               class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">Logo</label>
        <input type="file" name="logo" class="form-control form-control-sm"
               accept="image/*" id="logoInput" <?= !$isEdit ? 'disabled' : '' ?>>
    </div>

    <div class="col-md-3">
        <label class="small">Preview</label><br>
        <img src="<?=$settings['logo_path']?>" id="logoPreview"
             style="max-height:55px;border:1px solid #ddd">
    </div>

</div>
</div>
</div>

<!-- ADDRESS CARD -->
<div class="card border-0 shadow-sm mb-3">
<div class="card-header py-2 small fw-bold">Address</div>
<div class="card-body">
<div class="row g-3">
    <div class="col-md-4">
        <textarea name="address" rows="2"
            class="form-control form-control-sm"
            <?= !$isEdit ? 'readonly' : '' ?>><?=$settings['address']?></textarea>
    </div>
    <div class="col-md-2"><input type="text" name="city" value="<?=$settings['city']?>" class="form-control form-control-sm" placeholder="City" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-2"><input type="text" name="state" value="<?=$settings['state']?>" class="form-control form-control-sm" placeholder="State" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-2"><input type="text" name="pincode" value="<?=$settings['pincode']?>" class="form-control form-control-sm" placeholder="Pincode" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-2"><input type="text" name="country" value="<?=$settings['country']?>" class="form-control form-control-sm" placeholder="Country" <?= !$isEdit ? 'readonly' : '' ?>></div>
</div>
</div>
</div>

<!-- INVOICE CARD -->
<div class="card border-0 shadow-sm mb-3">
<div class="card-header py-2 small fw-bold">Invoice Settings</div>
<div class="card-body">
<div class="row g-3">
    <div class="col-md-2"><label class="small">Prefix</label><input type="text" name="invoice_prefix" value="<?=$settings['invoice_prefix']?>" class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-2"><label class="small">Next No</label><input type="number" name="next_invoice_no" value="<?=$settings['next_invoice_no']?>" class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-2">
        <label class="small">Tax Type</label>
        <select name="tax_type" class="form-select form-select-sm" <?= !$isEdit ? 'disabled' : '' ?>>
            <option <?=$settings['tax_type']=='CGST'?'selected':''?>>CGST</option>
            <option <?=$settings['tax_type']=='SGST'?'selected':''?>>SGST</option>
            <option <?=$settings['tax_type']=='IGST'?'selected':''?>>IGST</option>
        </select>
    </div>
    <div class="col-md-2"><label class="small">Tax %</label><input type="number" step="0.01" name="tax_percent" value="<?=$settings['tax_percent']?>" class="form-control form-control-sm" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-3 d-flex align-items-center">
        <input type="checkbox" name="show_delivery" <?= !$isEdit ? 'disabled' : '' ?> <?=$settings['show_delivery']?'checked':''?>>
        <span class="small ms-2">Show Delivery Address</span>
    </div>
</div>
</div>
</div>

<!-- BANK CARD -->
<div class="card border-0 shadow-sm mb-3">
<div class="card-header py-2 small fw-bold">Bank Details</div>
<div class="card-body">
<div class="row g-3">
    <div class="col-md-3"><input type="text" name="bank_name" value="<?=$settings['bank_name']?>" class="form-control form-control-sm" placeholder="Bank Name" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-3"><input type="text" name="bank_holder" value="<?=$settings['bank_holder']?>" class="form-control form-control-sm" placeholder="Account Holder" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-3"><input type="text" name="bank_account" value="<?=$settings['bank_account']?>" class="form-control form-control-sm" placeholder="Account No" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-2"><input type="text" name="ifsc_code" value="<?=$settings['ifsc_code']?>" class="form-control form-control-sm" placeholder="IFSC" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-3"><input type="text" name="branch" value="<?=$settings['branch']?>" class="form-control form-control-sm" placeholder="Branch" <?= !$isEdit ? 'readonly' : '' ?>></div>
    <div class="col-md-3"><input type="text" name="upi_id" value="<?=$settings['upi_id']?>" class="form-control form-control-sm" placeholder="UPI ID" <?= !$isEdit ? 'readonly' : '' ?>></div>
</div>
</div>
</div>

<!-- FOOTER CARD -->
<div class="card border-0 shadow-sm mb-3">
<div class="card-header py-2 small fw-bold">Invoice Footer</div>
<div class="card-body">
<div class="row g-3">
    <div class="col-md-6"><textarea name="invoice_terms" rows="3" class="form-control form-control-sm" placeholder="Terms & Conditions" <?= !$isEdit ? 'readonly' : '' ?>><?=$settings['invoice_terms']?></textarea></div>
    <div class="col-md-6"><textarea name="footer_notes" rows="3" class="form-control form-control-sm" placeholder="Footer Notes" <?= !$isEdit ? 'readonly' : '' ?>><?=$settings['footer_notes']?></textarea></div>
</div>
</div>
</div>

<!-- ACTION BUTTONS AT BOTTOM -->
<div class="text-end mt-3">
    <?php if (!$isEdit): ?>
        <a href="index.php?page=settings&edit=1" class="btn btn-primary btn-sm px-3">Edit</a>
    <?php else: ?>
        <button type="submit" form="settingsForm" class="btn btn-success btn-sm px-3">Save</button>
        <a href="index.php?page=settings" class="btn btn-secondary btn-sm px-3">Cancel</a>
    <?php endif; ?>
</div>

</form>
</div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>

<script>
// Preview new logo
document.getElementById('logoInput')?.addEventListener('change', e => {
    const f = e.target.files[0];
    if (f) document.getElementById('logoPreview').src = URL.createObjectURL(f);
});
</script>
