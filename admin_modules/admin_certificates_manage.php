<?php
// admin_modules/admin_certificates_manage.php
// Admin interface for issuing and managing student certificates

// Handle POST actions at top of file (called from admin.php before HTML output)
// These are handled in admin.php's POST handler section — this file is display-only

// Get all active students
$studentsRes = $db->query("SELECT id, full_name, email, domain_interest FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) $students[] = $row;

// Get all batches for dropdown
$batchesRes = $db->query("SELECT id, batch_name FROM internship_batches ORDER BY id DESC");
$batches = [];
if ($batchesRes) while ($row = $batchesRes->fetch_assoc()) $batches[] = $row;

// Get all certificates with student + batch info
$certsRes = $db->query("
    SELECT ic.*, s.full_name, s.email, s.domain_interest,
           ib.batch_name
    FROM internship_certificates ic
    JOIN internship_students s ON s.id = ic.student_id
    LEFT JOIN internship_batches ib ON ib.id = ic.batch_id
    ORDER BY ic.created_at DESC
    LIMIT 200
");
$allCerts = [];
while ($row = $certsRes->fetch_assoc()) $allCerts[] = $row;

// Stats
$certStats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN is_issued=1 THEN 1 ELSE 0 END) as issued,
    SUM(CASE WHEN is_issued=0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN completion_grade='Outstanding' THEN 1 ELSE 0 END) as outstanding,
    SUM(CASE WHEN completion_grade='Excellent' THEN 1 ELSE 0 END) as excellent
    FROM internship_certificates
")->fetch_assoc();
?>

<style>
/* ── Stats ── */
.cm-stats{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:12px;
    margin-bottom:20px;
}
.cm-stat{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:12px;
    padding:16px 18px;
    display:flex;align-items:center;gap:12px;
    transition:box-shadow .2s;
}
.cm-stat:hover{box-shadow:0 4px 16px rgba(0,0,0,0.07);}
.cm-stat-icon{
    width:40px;height:40px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:.9rem;flex-shrink:0;
}
.cm-stat-icon.p{background:rgba(139,92,246,0.1);color:#8b5cf6;}
.cm-stat-icon.g{background:rgba(34,197,94,0.1);color:#22c55e;}
.cm-stat-icon.y{background:rgba(234,179,8,0.1);color:#eab308;}
.cm-stat-icon.gold{background:rgba(251,191,36,0.1);color:#fbbf24;}
.cm-stat-icon.b{background:rgba(59,130,246,0.1);color:#3b82f6;}
.cm-stat-val{font-size:1.55rem;font-weight:800;color:var(--text);line-height:1;letter-spacing:-0.02em;}
.cm-stat-lbl{font-size:.7rem;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;}

/* ── Panel ── */
.cm-panel{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    margin-bottom:20px;
    overflow:hidden;
}
.cm-panel-head{
    padding:15px 22px;
    border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;background:var(--bg);
}
.cm-panel-title{
    font-size:.95rem;font-weight:700;color:var(--text);
    display:flex;align-items:center;gap:9px;
}
.cm-panel-title i{color:var(--o5);font-size:.88rem;}
.cm-panel-body{padding:22px;}

/* ── Form ── */
.cm-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.cm-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.cm-fg{margin-bottom:16px;}
.cm-fg:last-child{margin-bottom:0;}

.cm-label{
    display:flex;align-items:center;gap:6px;
    font-size:.75rem;font-weight:700;
    color:var(--text2);text-transform:uppercase;letter-spacing:.05em;
    margin-bottom:7px;
}
.cm-label i{color:var(--o5);font-size:.72rem;}
.cm-label .req{color:#ef4444;margin-left:1px;}

.cm-ctrl{
    width:100%;padding:10px 13px;
    border:1.5px solid var(--border);border-radius:9px;
    font-size:.875rem;font-family:inherit;color:var(--text);
    background:var(--card);outline:none;
    transition:border-color .18s,box-shadow .18s;
}
.cm-ctrl:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.cm-ctrl::placeholder{color:var(--text3);}
select.cm-ctrl{cursor:pointer;}

.cm-hint{font-size:.71rem;color:var(--text3);margin-top:5px;line-height:1.4;}

.cm-divider{
    display:flex;align-items:center;gap:12px;
    margin:18px 0 14px;
}
.cm-divider span{
    font-size:.68rem;font-weight:800;text-transform:uppercase;
    letter-spacing:.1em;color:var(--text3);white-space:nowrap;
}
.cm-divider::before,.cm-divider::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── Buttons ── */
.cm-btn{
    padding:10px 18px;border-radius:9px;
    font-size:.845rem;font-weight:700;font-family:inherit;
    cursor:pointer;border:none;
    display:inline-flex;align-items:center;gap:7px;
    text-decoration:none;transition:all .18s;
}
.cm-btn-primary{background:#8b5cf6;color:#fff;box-shadow:0 3px 12px rgba(139,92,246,0.3);}
.cm-btn-primary:hover{background:#7c3aed;box-shadow:0 5px 18px rgba(139,92,246,0.4);transform:translateY(-1px);}
.cm-btn-success{background:#22c55e;color:#fff;box-shadow:0 3px 10px rgba(34,197,94,0.3);}
.cm-btn-success:hover{background:#16a34a;transform:translateY(-1px);}
.cm-btn-ghost{background:transparent;border:1.5px solid var(--border);color:var(--text2);}
.cm-btn-ghost:hover{border-color:var(--text2);color:var(--text);}
.cm-btn-danger{background:#fff1f1;border:1.5px solid #fecaca;color:#dc2626;}
.cm-btn-danger:hover{background:#dc2626;color:#fff;border-color:#dc2626;}
.cm-btn-warning{background:#fffbeb;border:1.5px solid #fde68a;color:#b45309;}
.cm-btn-warning:hover{background:#f59e0b;color:#fff;border-color:#f59e0b;}
.cm-btn-sm{padding:6px 11px;font-size:.75rem;}

.cm-form-actions{
    display:flex;gap:10px;
    padding-top:18px;border-top:1px solid var(--border);margin-top:18px;
}

/* ── Table ── */
.cm-table-wrap{overflow-x:auto;}
.cm-table{width:100%;border-collapse:collapse;font-size:.845rem;}
.cm-table thead tr{border-bottom:2px solid var(--border);}
.cm-table th{
    padding:11px 14px;
    font-size:.68rem;font-weight:800;color:var(--text3);
    text-transform:uppercase;letter-spacing:.07em;
    text-align:left;background:var(--bg);white-space:nowrap;
}
.cm-table tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
.cm-table tbody tr:last-child{border-bottom:none;}
.cm-table tbody tr:hover{background:rgba(139,92,246,0.03);}
.cm-table td{padding:13px 14px;color:var(--text);vertical-align:middle;}

/* ── Table Cells ── */
.cm-student{display:flex;align-items:center;gap:10px;}
.cm-avatar{
    width:34px;height:34px;border-radius:9px;
    background:linear-gradient(135deg,#8b5cf6,#3b82f6);
    display:flex;align-items:center;justify-content:center;
    font-size:.78rem;font-weight:800;color:#fff;flex-shrink:0;
}
.cm-student-name{font-weight:600;color:var(--text);font-size:.845rem;}
.cm-student-email{font-size:.71rem;color:var(--text3);margin-top:1px;}

.cm-cert-num{
    font-family:'Courier New',monospace;
    font-size:.78rem;font-weight:700;color:var(--text2);
    padding:3px 8px;background:var(--bg);
    border-radius:5px;border:1px solid var(--border);
    display:inline-block;
}

.cm-grade{
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 9px;border-radius:6px;
    font-size:.7rem;font-weight:700;
}
.cm-grade.outstanding{background:rgba(251,191,36,0.15);color:#a16207;}
.cm-grade.excellent{background:rgba(34,197,94,0.15);color:#15803d;}
.cm-grade.good{background:rgba(59,130,246,0.15);color:#1d4ed8;}
.cm-grade.satisfactory{background:rgba(139,92,246,0.15);color:#6d28d9;}

.cm-status{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;border-radius:20px;
    font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
}
.cm-status::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.cm-status.issued{background:rgba(34,197,94,0.1);color:#15803d;}
.cm-status.issued::before{background:#22c55e;}
.cm-status.pending{background:rgba(234,179,8,0.1);color:#a16207;}
.cm-status.pending::before{background:#eab308;}

.cm-date{font-size:.8rem;color:var(--text2);}
.cm-na{font-size:.78rem;color:var(--text3);font-style:italic;}
.cm-actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap;}

/* ── Empty ── */
.cm-empty{text-align:center;padding:48px 24px;color:var(--text3);}
.cm-empty-icon{
    width:50px;height:50px;margin:0 auto 12px;
    background:var(--bg);border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.2rem;border:1px solid var(--border);opacity:.7;
}
.cm-empty p{font-size:.875rem;font-weight:500;margin:0;}

/* ── Modal ── */
.cm-modal{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,0.55);backdrop-filter:blur(5px);
    z-index:9999;padding:20px;overflow-y:auto;
    align-items:center;justify-content:center;
}
.cm-modal.open{display:flex;}
.cm-modal-box{
    background:var(--card);border-radius:16px;
    max-width:620px;width:100%;
    box-shadow:0 24px 64px rgba(0,0,0,0.22);
    animation:cmModalIn .25s ease;
}
@keyframes cmModalIn{from{opacity:0;transform:translateY(-14px) scale(.98);}to{opacity:1;transform:none;}}
.cm-modal-head{
    padding:17px 22px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
}
.cm-modal-title{font-size:1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:9px;}
.cm-modal-title i{color:#8b5cf6;}
.cm-modal-close{
    width:30px;height:30px;border-radius:8px;border:none;
    background:var(--bg);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    color:var(--text3);font-size:.82rem;transition:all .15s;
}
.cm-modal-close:hover{background:var(--border);color:var(--text);}
.cm-modal-body{padding:22px;max-height:68vh;overflow-y:auto;}

/* ── Detail rows ── */
.cm-detail-grid{display:grid;gap:13px;}
.cm-detail-lbl{font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:4px;}
.cm-detail-val{font-size:.875rem;color:var(--text);font-weight:500;line-height:1.5;}
.cm-detail-2col{display:grid;grid-template-columns:1fr 1fr;gap:13px;}
.cm-detail-sep{border:none;border-top:1px solid var(--border);margin:2px 0;}

/* ── Issue Form in Modal ── */
.cm-issue-form{padding:20px 22px;border-top:1px solid var(--border);}
.cm-issue-title{font-size:.85rem;font-weight:700;color:var(--text);margin-bottom:14px;display:flex;align-items:center;gap:7px;}
.cm-issue-title i{color:#22c55e;}

@media(max-width:1200px){.cm-stats{grid-template-columns:repeat(3,1fr);}}
@media(max-width:820px){
    .cm-stats{grid-template-columns:repeat(2,1fr);}
    .cm-grid-2,.cm-grid-3{grid-template-columns:1fr;}
}
@media(max-width:500px){.cm-stats{grid-template-columns:1fr;}}
</style>

<!-- ── Stats ── -->
<div class="cm-stats">
    <div class="cm-stat">
        <div class="cm-stat-icon p"><i class="fas fa-certificate"></i></div>
        <div>
            <div class="cm-stat-val"><?php echo number_format($certStats['total']); ?></div>
            <div class="cm-stat-lbl">Total</div>
        </div>
    </div>
    <div class="cm-stat">
        <div class="cm-stat-icon g"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="cm-stat-val"><?php echo number_format($certStats['issued']); ?></div>
            <div class="cm-stat-lbl">Issued</div>
        </div>
    </div>
    <div class="cm-stat">
        <div class="cm-stat-icon y"><i class="fas fa-clock"></i></div>
        <div>
            <div class="cm-stat-val"><?php echo number_format($certStats['pending']); ?></div>
            <div class="cm-stat-lbl">Pending</div>
        </div>
    </div>
    <div class="cm-stat">
        <div class="cm-stat-icon gold"><i class="fas fa-trophy"></i></div>
        <div>
            <div class="cm-stat-val"><?php echo number_format($certStats['outstanding']); ?></div>
            <div class="cm-stat-lbl">Outstanding</div>
        </div>
    </div>
    <div class="cm-stat">
        <div class="cm-stat-icon b"><i class="fas fa-award"></i></div>
        <div>
            <div class="cm-stat-val"><?php echo number_format($certStats['excellent']); ?></div>
            <div class="cm-stat-lbl">Excellent</div>
        </div>
    </div>
</div>

<!-- ── Issue New Certificate ── -->
<div class="cm-panel">
    <div class="cm-panel-head">
        <div class="cm-panel-title">
            <i class="fas fa-plus-circle"></i>
            Issue New Certificate
        </div>
    </div>
    <div class="cm-panel-body">
        <form method="POST" action="admin.php">
            <input type="hidden" name="issue_certificate" value="1">

            <div class="cm-grid-2">
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-user"></i> Student <span class="req">*</span></label>
                    <select name="student_id" class="cm-ctrl" required>
                        <option value="">Choose a student...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>">
                            <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['domain_interest'] ?: 'No domain'); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-layer-group"></i> Batch</label>
                    <select name="batch_id" class="cm-ctrl">
                        <option value="">Select batch (optional)</option>
                        <?php foreach ($batches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['batch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cm-grid-3">
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-id-card"></i> Certificate Number</label>
                    <input type="text" name="certificate_number" class="cm-ctrl"
                           placeholder="e.g. PADAK-2025-001"
                           value="PADAK-<?php echo date('Y'); ?>-<?php echo str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT); ?>">
                    <div class="cm-hint">Leave as-is or enter a custom number</div>
                </div>
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-calendar"></i> Issued Date</label>
                    <input type="date" name="issued_date" class="cm-ctrl"
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-star"></i> Completion Grade <span class="req">*</span></label>
                    <select name="completion_grade" class="cm-ctrl" required>
                        <option value="Good" selected>Good</option>
                        <option value="Satisfactory">Satisfactory</option>
                        <option value="Excellent">Excellent</option>
                        <option value="Outstanding">Outstanding</option>
                    </select>
                </div>
            </div>

            <div class="cm-grid-2">
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-coins"></i> Total Points Earned</label>
                    <input type="number" name="total_points_earned" class="cm-ctrl" value="0" min="0">
                </div>
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-link"></i> Certificate URL</label>
                    <input type="url" name="certificate_url" class="cm-ctrl"
                           placeholder="https://example.com/cert/view/...">
                    <div class="cm-hint">Public URL for certificate verification page</div>
                </div>
            </div>

            <div class="cm-fg">
                <label class="cm-label"><i class="fas fa-file-pdf"></i> Certificate File Path / URL</label>
                <input type="text" name="certificate_file" class="cm-ctrl"
                       placeholder="e.g. certificates/2025/padak-cert-001.pdf or https://...">
                <div class="cm-hint">File path or direct download URL. Student will use this for the Download button.</div>
            </div>

            <div class="cm-divider"><span>Issue Status</span></div>

            <div class="cm-grid-2">
                <div class="cm-fg">
                    <label class="cm-label"><i class="fas fa-toggle-on"></i> Issue Immediately?</label>
                    <select name="is_issued" class="cm-ctrl">
                        <option value="1">Yes — Issue Now</option>
                        <option value="0">No — Keep Pending</option>
                    </select>
                    <div class="cm-hint">Pending certificates show a "Certificate Pending" button to the student</div>
                </div>
            </div>

            <div class="cm-form-actions">
                <button type="submit" class="cm-btn cm-btn-primary">
                    <i class="fas fa-certificate"></i> Issue Certificate
                </button>
                <button type="reset" class="cm-btn cm-btn-ghost">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── All Certificates ── -->
<div class="cm-panel">
    <div class="cm-panel-head">
        <div class="cm-panel-title">
            <i class="fas fa-list"></i>
            All Certificates
        </div>
    </div>
    <div class="cm-table-wrap">
        <table class="cm-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Certificate #</th>
                    <th>Batch</th>
                    <th>Grade</th>
                    <th>Points</th>
                    <th>Status</th>
                    <th>Issued Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allCerts)): ?>
                <tr>
                    <td colspan="8">
                        <div class="cm-empty">
                            <div class="cm-empty-icon"><i class="fas fa-certificate"></i></div>
                            <p>No certificates issued yet</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($allCerts as $cert): ?>
                    <?php
                        $initials = '';
                        foreach (array_slice(explode(' ', trim($cert['full_name'])), 0, 2) as $p)
                            $initials .= strtoupper($p[0] ?? '');
                        $isIssued = (bool)$cert['is_issued'];
                    ?>
                    <tr>
                        <td>
                            <div class="cm-student">
                                <div class="cm-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                <div>
                                    <div class="cm-student-name"><?php echo htmlspecialchars($cert['full_name']); ?></div>
                                    <div class="cm-student-email"><?php echo htmlspecialchars($cert['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($cert['certificate_number'])): ?>
                            <span class="cm-cert-num"><?php echo htmlspecialchars($cert['certificate_number']); ?></span>
                            <?php else: ?>
                            <span class="cm-na">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="cm-date"><?php echo htmlspecialchars($cert['batch_name'] ?? '—'); ?></td>
                        <td>
                            <?php if (!empty($cert['completion_grade'])): ?>
                            <span class="cm-grade <?php echo strtolower($cert['completion_grade']); ?>">
                                <?php echo htmlspecialchars($cert['completion_grade']); ?>
                            </span>
                            <?php else: ?>
                            <span class="cm-na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="cm-date"><?php echo number_format($cert['total_points_earned'] ?? 0); ?></td>
                        <td>
                            <span class="cm-status <?php echo $isIssued ? 'issued' : 'pending'; ?>">
                                <?php echo $isIssued ? 'Issued' : 'Pending'; ?>
                            </span>
                        </td>
                        <td class="cm-date">
                            <?php echo !empty($cert['issued_date']) ? date('M d, Y', strtotime($cert['issued_date'])) : '<span class="cm-na">—</span>'; ?>
                        </td>
                        <td>
                            <div class="cm-actions">
                                <!-- View -->
                                <button class="cm-btn cm-btn-ghost cm-btn-sm"
                                        title="View details"
                                        onclick="cmViewCert(<?php echo htmlspecialchars(json_encode($cert)); ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <!-- Toggle issue status -->
                                <?php if (!$isIssued): ?>
                                <form method="POST" action="admin.php" style="display:inline;"
                                      onsubmit="return confirm('Issue this certificate now?')">
                                    <input type="hidden" name="mark_cert_issued" value="1">
                                    <input type="hidden" name="cert_id" value="<?php echo $cert['id']; ?>">
                                    <button type="submit" class="cm-btn cm-btn-success cm-btn-sm" title="Mark as Issued">
                                        <i class="fas fa-check"></i> Issue
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" action="admin.php" style="display:inline;"
                                      onsubmit="return confirm('Revoke / set back to pending?')">
                                    <input type="hidden" name="revoke_certificate" value="1">
                                    <input type="hidden" name="cert_id" value="<?php echo $cert['id']; ?>">
                                    <button type="submit" class="cm-btn cm-btn-warning cm-btn-sm" title="Revoke">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Delete -->
                                <form method="POST" action="admin.php" style="display:inline;"
                                      onsubmit="return confirm('Permanently delete this certificate? This cannot be undone.')">
                                    <input type="hidden" name="delete_certificate" value="1">
                                    <input type="hidden" name="cert_id" value="<?php echo $cert['id']; ?>">
                                    <button type="submit" class="cm-btn cm-btn-danger cm-btn-sm" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── View/Edit Modal ── -->
<div class="cm-modal" id="cmViewModal">
    <div class="cm-modal-box">
        <div class="cm-modal-head">
            <div class="cm-modal-title"><i class="fas fa-certificate"></i> Certificate Details</div>
            <button class="cm-modal-close" onclick="cmCloseModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="cm-modal-body" id="cmModalContent"></div>

        <!-- Quick Edit: issue date + file + status update -->
        <div class="cm-issue-form">
            <div class="cm-issue-title"><i class="fas fa-edit"></i> Quick Update</div>
            <form method="POST" action="admin.php" id="cmQuickEditForm">
                <input type="hidden" name="update_certificate" value="1">
                <input type="hidden" name="cert_id" id="cmEditId">
                <div class="cm-grid-2" style="margin-bottom:14px;">
                    <div class="cm-fg" style="margin:0;">
                        <label class="cm-label"><i class="fas fa-calendar"></i> Issued Date</label>
                        <input type="date" name="issued_date" id="cmEditDate" class="cm-ctrl">
                    </div>
                    <div class="cm-fg" style="margin:0;">
                        <label class="cm-label"><i class="fas fa-star"></i> Grade</label>
                        <select name="completion_grade" id="cmEditGrade" class="cm-ctrl">
                            <option value="Satisfactory">Satisfactory</option>
                            <option value="Good">Good</option>
                            <option value="Excellent">Excellent</option>
                            <option value="Outstanding">Outstanding</option>
                        </select>
                    </div>
                </div>
                <div class="cm-fg" style="margin-bottom:14px;">
                    <label class="cm-label"><i class="fas fa-file-pdf"></i> Certificate File / URL</label>
                    <input type="text" name="certificate_file" id="cmEditFile" class="cm-ctrl" placeholder="Path or URL...">
                </div>
                <div class="cm-fg" style="margin-bottom:14px;">
                    <label class="cm-label"><i class="fas fa-coins"></i> Points Earned</label>
                    <input type="number" name="total_points_earned" id="cmEditPoints" class="cm-ctrl" min="0">
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="cm-btn cm-btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="cm-btn cm-btn-ghost" onclick="cmCloseModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const cmGradeColors = {
    Outstanding:'#a16207', Excellent:'#15803d', Good:'#1d4ed8', Satisfactory:'#6d28d9'
};

function cmViewCert(c) {
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : null;

    document.getElementById('cmModalContent').innerHTML = `
        <div class="cm-detail-grid">
            <div>
                <div class="cm-detail-lbl">Student</div>
                <div class="cm-detail-val" style="font-weight:700;">${c.full_name}</div>
                <div class="cm-detail-val" style="font-size:.77rem;color:var(--text3);">${c.email}</div>
            </div>
            <hr class="cm-detail-sep">
            <div class="cm-detail-2col">
                <div>
                    <div class="cm-detail-lbl">Certificate #</div>
                    <div class="cm-detail-val" style="font-family:monospace;font-weight:700;">${c.certificate_number || '—'}</div>
                </div>
                <div>
                    <div class="cm-detail-lbl">Batch</div>
                    <div class="cm-detail-val">${c.batch_name || '—'}</div>
                </div>
                <div>
                    <div class="cm-detail-lbl">Grade</div>
                    <div class="cm-detail-val" style="font-weight:700;color:${cmGradeColors[c.completion_grade]||'var(--text)'};">${c.completion_grade || '—'}</div>
                </div>
                <div>
                    <div class="cm-detail-lbl">Points</div>
                    <div class="cm-detail-val">${Number(c.total_points_earned||0).toLocaleString()}</div>
                </div>
                <div>
                    <div class="cm-detail-lbl">Status</div>
                    <div class="cm-detail-val" style="font-weight:700;color:${c.is_issued=='1'?'#15803d':'#a16207'};">
                        ${c.is_issued=='1' ? 'Issued' : 'Pending'}
                    </div>
                </div>
                <div>
                    <div class="cm-detail-lbl">Issued Date</div>
                    <div class="cm-detail-val">${fmt(c.issued_date) || '—'}</div>
                </div>
            </div>
            ${c.certificate_file ? `<div>
                <div class="cm-detail-lbl">File / URL</div>
                <div class="cm-detail-val" style="word-break:break-all;font-size:.78rem;">${c.certificate_file}</div>
            </div>` : ''}
        </div>`;

    // Pre-fill quick edit
    document.getElementById('cmEditId').value    = c.id;
    document.getElementById('cmEditDate').value  = c.issued_date || '';
    document.getElementById('cmEditGrade').value = c.completion_grade || 'Good';
    document.getElementById('cmEditFile').value  = c.certificate_file || '';
    document.getElementById('cmEditPoints').value = c.total_points_earned || 0;

    document.getElementById('cmViewModal').classList.add('open');
}

function cmCloseModal() {
    document.getElementById('cmViewModal').classList.remove('open');
}
document.getElementById('cmViewModal').addEventListener('click', function(e) {
    if (e.target === this) cmCloseModal();
});
</script>