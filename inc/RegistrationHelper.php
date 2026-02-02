<?php
// usms/inc/RegistrationHelper.php
// ACID-Compliant Registration Fee Handler

class RegistrationHelper {
    
    /**
     * Marks a member as paid and records the transaction in the General Ledger.
     */
    /**
     * Marks a member as paid and records the transaction in the General Ledger.
     */
    public static function markAsPaid($member_id, $amount, $ref_no, $conn) {
        $conn->begin_transaction();
        
        try {
            // 1. Update Member Table
            $sqlMem = "UPDATE members SET registration_fee_status = 'paid' WHERE member_id = ?";
            $stmtMem = $conn->prepare($sqlMem);
            $stmtMem->bind_param("i", $member_id);
            if (!$stmtMem->execute()) throw new Exception("Failed to update member status.");
            $stmtMem->close();

            // 2. Record in Ledger via TransactionHelper (The Golden Ledger)
            // Ensure TransactionHelper is loaded
            if (!class_exists('TransactionHelper')) {
                require_once __DIR__ . '/TransactionHelper.php';
            }
            
            TransactionHelper::setConnection($conn);
            $recordSuccess = TransactionHelper::record([
                'type' => 'income',
                'amount' => $amount,
                'member_id' => $member_id,
                'related_table' => 'registration_fee',
                'ref_no' => $ref_no,
                'notes' => 'One-time Registration Fee Payment',
                'update_member_balance' => false, // Fee does not increase savings
                'use_external_txn' => true // We are already in a transaction
            ]);

            if (!$recordSuccess) {
                throw new Exception("TransactionHelper failed to record entry.");
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Registration Payment Error: " . $e->getMessage());
            return false;
        }
    }
}
