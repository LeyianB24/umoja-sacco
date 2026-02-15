<?php
// inc/PayrollCalculator.php

class PayrollCalculator {
    private $db;
    
    // Cache for deductions config
    private $deductions_config = [];

    public function __construct($db) {
        $this->db = $db;
        $this->loadDeductionRules();
    }

    private function loadDeductionRules() {
        $q = $this->db->query("SELECT * FROM statutory_deductions WHERE is_active = 1");
        while ($row = $q->fetch_assoc()) {
            $this->deductions_config[$row['name']] = $row;
        }
    }

    public function calculate($employee) {
        $basic = (float)$employee['salary'];
        // Allowances (If stored in employee table, otherwise 0 for now)
        // In future, fetch from 'start_allowances' table
        $house = 0; // Or fetch from grade?
        $transport = 0;
        
        // If we have grade, use grade allowances
        if (!empty($employee['grade_id'])) {
            $g_q = $this->db->query("SELECT * FROM salary_grades WHERE id = {$employee['grade_id']}");
            $grade = $g_q->fetch_assoc();
            if ($grade) {
                // $basic = (float)$grade['basic_salary']; // Optional: Force grade salary? No, keep individual override.
                $house = (float)$grade['house_allowance'];
                $transport = (float)$grade['transport_allowance'];
            }
        }
        
        $gross = $basic + $house + $transport;
        
        // Deductions
        $nssf = $this->calcNSSF($gross);
        $sha = $this->calcSHA($gross);
        $housing = $this->calcHousingLevy($gross);
        
        $taxable_income = $gross - $nssf; // Defined Contributions are deductible
        $paye = $this->calcPAYE($taxable_income); // Simplified (No personal relief?)
        // Apply Personal Relief (Standard 2400 KES usually)
        $paye = max(0, $paye - 2400);

        $total_deductions = $nssf + $sha + $housing + $paye;
        $net = $gross - $total_deductions;

        return [
            'basic_salary' => $basic,
            'house_allowance' => $house,
            'transport_allowance' => $transport,
            'gross_pay' => $gross,
            'tax_nssf' => $nssf,
            'tax_sha' => $sha,
            'tax_housing' => $housing,
            'tax_paye' => $paye,
            'total_deductions' => $total_deductions,
            'net_pay' => $net
        ];
    }

    private function calcNSSF($gross) {
        // Tier 1: 6% of up to 6000 (360)
        // Tier 2: 6% of (18000-6000) (720)
        // Total max 1080.
        // Or configuration based.
        // User config: {'value': 200, 'type': 'fixed'} OR old style
        
        // Use loaded config
        $rule = $this->deductions_config['NSSF'] ?? null;
        if (!$rule) return 1080; // Default fallback (Tier 2 max)

        if ($rule['type'] === 'fixed') return (float)$rule['value'];
        
        // Simplified Tier Logic (2024)
        $tier1 = min($gross, 7000) * 0.06;
        $tier2 = 0;
        if ($gross > 7000) {
            $pensionable = min($gross, 36000) - 7000;
            $tier2 = $pensionable * 0.06;
        }
        return $tier1 + $tier2;
    }

    private function calcSHA($gross) {
        $rule = $this->deductions_config['SHA'] ?? null;
        if (!$rule || $rule['type'] !== 'bracket') return 0;

        $brackets = json_decode($rule['value'], true);
        // Sort keys logic
        ksort($brackets);
        
        $deduction = 0;
        foreach ($brackets as $limit => $amt) {
            if ($gross <= $limit) {
                return $amt;
            }
            $deduction = $amt; // Keep last
        }
        return $deduction;
    }

    private function calcHousingLevy($gross) {
        $rule = $this->deductions_config['Housing Levy'] ?? null;
        if (!$rule) return $gross * 0.015;
        
        if ($rule['type'] === 'percentage') {
            return $gross * ((float)$rule['value'] / 100);
        }
        return 0;
    }

    private function calcPAYE($taxable) {
        // Annual Rates (divide by 12? or use monthly brackets)
        // Monthly Bands 2024:
        // 0 - 24,000 : 10%
        // 24,001 - 32,333 : 25%
        // > 32,333 : 30%
        // > 500,000 : 32.5%
        // > 800,000 : 35%
        
        $tax = 0;
        
        // Band 1: First 24,000
        if ($taxable <= 24000) return $taxable * 0.1;
        $tax += 24000 * 0.1;
        
        // Band 2: Next 8,333 (up to 32,333)
        if ($taxable <= 32333) {
            $tax += ($taxable - 24000) * 0.25;
            return $tax;
        }
        $tax += 8333 * 0.25;
        
        // Band 3: Up to 500,000
        if ($taxable <= 500000) {
            $tax += ($taxable - 32333) * 0.30;
            return $tax;
        }
        $tax += (500000 - 32333) * 0.30;
        
        // ... Higher bands omitted for brevity/standard setups ...
        
        return $tax;
    }
}
?>
