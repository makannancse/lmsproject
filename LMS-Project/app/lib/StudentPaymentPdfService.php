<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/helpers.php';

class StudentPaymentPdfService
{
    /**
     * Generate and stream/download a PDF payment statement for the given payment records.
     *
     * @param list<array<string, mixed>> $payments
     * @param array{total_amount: float, pending_amount: float, paid_amount: float, total_count: int} $summary
     * @param array<string, mixed> $filters
     */
    public static function streamPdf(array $payments, array $summary, array $filters = []): void
    {
        $html = self::buildHtml($payments, $summary, $filters);

        $projectRoot = dirname(__DIR__, 2);
        $autoload = $projectRoot . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            echo 'Dompdf library is not installed. Please run composer install.';
            exit;
        }

        @ini_set('memory_limit', '256M');
        @ini_set('max_execution_time', '120');

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->setChroot($projectRoot);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Student_Payment_Statement_' . date('Ymd_His') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $dompdf->output();
        exit;
    }

    /**
     * Build clean, styled HTML string for PDF rendering.
     *
     * @param list<array<string, mixed>> $payments
     * @param array{total_amount: float, pending_amount: float, paid_amount: float, total_count: int} $summary
     * @param array<string, mixed> $filters
     */
    public static function buildHtml(array $payments, array $summary, array $filters = []): string
    {
        $esc = static function (mixed $v): string {
            return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $brand = defined('APP_NAME') && APP_NAME !== 'LMS' ? APP_NAME : 'LearnWise';
        $today = date('d M Y');
        $stmtNo = 'STMT-' . date('Ymd-His');

        // Extract student details if single student is selected or from first row
        $studentName = '';
        $studentEmail = '';
        $parentEmail = '';

        if (!empty($payments)) {
            $studentName = (string) ($payments[0]['student_name'] ?? '');
            $studentEmail = (string) ($payments[0]['student_email'] ?? '');
            $parentEmail = (string) ($payments[0]['parent_email'] ?? '');
        }

        $statusFilter = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $statusLabel = $statusFilter === 'pending' ? 'Pending Fees' : ($statusFilter === 'paid' ? 'Paid Fees' : 'All Fees');

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $dateScope = 'All Dates';
        if ($dateFrom !== '' && $dateTo !== '') {
            $dateScope = date('d M Y', strtotime($dateFrom)) . ' – ' . date('d M Y', strtotime($dateTo));
        } elseif ($dateFrom !== '') {
            $dateScope = 'From ' . date('d M Y', strtotime($dateFrom));
        } elseif ($dateTo !== '') {
            $dateScope = 'Until ' . date('d M Y', strtotime($dateTo));
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Payment Statement</title>
<style>
    @page { margin: 25pt 30pt 40pt 30pt; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
    .header { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; }
    .brand { font-size: 24px; font-weight: bold; color: #1e3a8a; }
    .sub-brand { font-size: 11px; color: #64748b; font-weight: normal; }
    .title-box { text-align: right; vertical-align: top; }
    .stmt-title { font-size: 16px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
    .meta-text { font-size: 9px; color: #64748b; margin-top: 4px; }
    
    .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .info-table td { width: 50%; vertical-align: top; padding: 0; }
    .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-right: 6px; }
    .card-right { margin-right: 0; margin-left: 6px; }
    .card-title { font-size: 10px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
    .info-row { font-size: 9.5px; margin-bottom: 3px; }
    .info-lbl { color: #64748b; width: 85px; display: inline-block; }
    .info-val { font-weight: bold; color: #0f172a; }

    .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .summary-box { background: #eff6ff; border: 1px solid #bfdbfe; text-align: center; padding: 8px; border-radius: 4px; }
    .summary-label { font-size: 8.5px; color: #1e40af; text-transform: uppercase; font-weight: bold; }
    .summary-value { font-size: 14px; font-weight: bold; color: #1e3a8a; margin-top: 2px; }
    .summary-pending { background: #fffbeb; border-color: #fde68a; }
    .summary-pending .summary-label { color: #92400e; }
    .summary-pending .summary-value { color: #b45309; }
    .summary-paid { background: #f0fdf4; border-color: #bbf7d0; }
    .summary-paid .summary-label { color: #166534; }
    .summary-paid .summary-value { color: #15803d; }

    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .items-table th { background: #1e3a8a; color: #ffffff; font-weight: bold; text-align: left; padding: 7px 8px; font-size: 9px; text-transform: uppercase; }
    .items-table th.num { text-align: right; }
    .items-table td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; vertical-align: middle; }
    .items-table tr:nth-child(even) td { background: #f8fafc; }
    .items-table td.num { text-align: right; font-weight: bold; }
    
    .badge { padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; text-transform: uppercase; display: inline-block; }
    .badge-paid { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde047; }

    .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .totals-table { width: 240px; margin-left: auto; border-collapse: collapse; }
    .totals-table td { padding: 4px 8px; font-size: 9.5px; }
    .totals-table .lbl { color: #64748b; text-align: right; }
    .totals-table .val { text-align: right; font-weight: bold; }
    .totals-table .due-row td { border-top: 2px solid #1e3a8a; font-size: 11px; font-weight: bold; color: #1e3a8a; padding-top: 6px; }

    .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>

<table class="header">
  <tr>
    <td>
      <div class="brand">' . $esc($brand) . '</div>
      <div class="sub-brand">Online Learning Management System</div>
    </td>
    <td class="title-box">
      <div class="stmt-title">Fee Statement</div>
      <div class="meta-text">Ref: ' . $esc($stmtNo) . '</div>
      <div class="meta-text">Date: ' . $esc($today) . '</div>
    </td>
  </tr>
</table>

<table class="info-table">
  <tr>
    <td>
      <div class="card">
        <div class="card-title">Student Information</div>
        <div class="info-row"><span class="info-lbl">Student Name:</span> <span class="info-val">' . $esc($studentName !== '' ? $studentName : 'All Students') . '</span></div>
        <div class="info-row"><span class="info-lbl">Student Email:</span> <span class="info-val">' . $esc($studentEmail !== '' ? $studentEmail : '—') . '</span></div>
        <div class="info-row"><span class="info-lbl">Parent Email:</span> <span class="info-val">' . $esc($parentEmail !== '' ? $parentEmail : '—') . '</span></div>
      </div>
    </td>
    <td>
      <div class="card card-right">
        <div class="card-title">Statement Scope</div>
        <div class="info-row"><span class="info-lbl">Status Filter:</span> <span class="info-val">' . $esc($statusLabel) . '</span></div>
        <div class="info-row"><span class="info-lbl">Date Range:</span> <span class="info-val">' . $esc($dateScope) . '</span></div>
        <div class="info-row"><span class="info-lbl">Total Records:</span> <span class="info-val">' . (int) $summary['total_count'] . ' Classes</span></div>
      </div>
    </td>
  </tr>
</table>

<table class="summary-grid">
  <tr>
    <td style="width: 32%; padding-right: 6px;">
      <div class="summary-box summary-pending">
        <div class="summary-label">Total Pending (Due)</div>
        <div class="summary-value">' . $esc(formatCurrency($summary['pending_amount'])) . '</div>
      </div>
    </td>
    <td style="width: 32%; padding: 0 3px;">
      <div class="summary-box summary-paid">
        <div class="summary-label">Total Paid</div>
        <div class="summary-value">' . $esc(formatCurrency($summary['paid_amount'])) . '</div>
      </div>
    </td>
    <td style="width: 32%; padding-left: 6px;">
      <div class="summary-box">
        <div class="summary-label">Total Statement Amount</div>
        <div class="summary-value">' . $esc(formatCurrency($summary['total_amount'])) . '</div>
      </div>
    </td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width: 25px;">#</th>
      <th>Student</th>
      <th>Class Title</th>
      <th>Date &amp; Time</th>
      <th>Teacher</th>
      <th>Status</th>
      <th class="num">Amount</th>
    </tr>
  </thead>
  <tbody>';

        if (empty($payments)) {
            $html .= '<tr><td colspan="7" style="text-align: center; color: #94a3b8; padding: 14px;">No class fee records found for the selected filters.</td></tr>';
        } else {
            $i = 1;
            foreach ($payments as $p) {
                $status = strtolower(trim((string) ($p['status'] ?? 'pending')));
                $badgeCls = $status === 'paid' ? 'badge-paid' : 'badge-pending';
                $statusText = strtoupper($status);
                $amount = (float) ($p['amount'] ?? 0);
                $dt = formatClassScheduledAt($p, 'd M Y h:i A');

                $html .= '<tr>
                    <td>' . $i++ . '</td>
                    <td>
                        <div style="font-weight: bold;">' . $esc((string) ($p['student_name'] ?? '—')) . '</div>
                        <div style="color: #64748b; font-size: 8px;">' . $esc((string) ($p['student_email'] ?? '')) . '</div>
                    </td>
                    <td>' . $esc((string) ($p['class_title'] ?? 'Class Session')) . '</td>
                    <td>' . $esc($dt) . '</td>
                    <td>' . $esc((string) ($p['teacher_name'] ?? '—')) . '</td>
                    <td><span class="badge ' . $badgeCls . '">' . $statusText . '</span></td>
                    <td class="num">' . $esc(formatCurrency($amount)) . '</td>
                </tr>';
            }
        }

        $html .= '  </tbody>
</table>

<table class="totals-wrap">
  <tr>
    <td></td>
    <td style="width: 250px;">
      <table class="totals-table">
        <tr>
          <td class="lbl">Total Pending (Due):</td>
          <td class="val" style="color: #b45309;">' . $esc(formatCurrency($summary['pending_amount'])) . '</td>
        </tr>
        <tr>
          <td class="lbl">Total Paid:</td>
          <td class="val" style="color: #15803d;">' . $esc(formatCurrency($summary['paid_amount'])) . '</td>
        </tr>
        <tr class="due-row">
          <td class="lbl">Balance Due:</td>
          <td class="val">' . $esc(formatCurrency($summary['pending_amount'])) . '</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="footer">
  &copy; ' . $esc(date('Y')) . ' ' . $esc($brand) . ' &bull; Official Fee Statement &bull; Thank you for choosing ' . $esc($brand) . '
</div>

</body>
</html>';

        return $html;
    }
}
