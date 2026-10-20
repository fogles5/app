<?php

class CoinsSystem {
    private $db;
    
    // Coin prices (in USD)
    const COIN_PACKAGES = [
        '100' => 4.99,
        '500' => 19.99,
        '1000' => 34.99,
        '5000' => 149.99,
        '10000' => 249.99
    ];
    
    // Revenue split
    const PLATFORM_FEE = 20; // 20% platform fee, 80% to creator
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get user's coin balance
     */
    public function getBalance($user_id) {
        $query = "SELECT balance FROM user_coins WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if(!$result) {
            // Create coin account if doesn't exist
            $this->createCoinAccount($user_id);
            return 0.00;
        }
        
        return floatval($result['balance']);
    }
    
    /**
     * Create coin account for user
     */
    private function createCoinAccount($user_id) {
        $query = "INSERT INTO user_coins (user_id, balance) VALUES (:user_id, 0) 
                  ON DUPLICATE KEY UPDATE user_id = user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }
    
    /**
     * Add coins to user account
     */
    public function addCoins($user_id, $amount, $type = 'purchase', $description = '', $reference_type = null, $reference_id = null, $bitcoin_tx_hash = null) {
        try {
            $this->db->beginTransaction();
            
            // Update balance
            $query = "INSERT INTO user_coins (user_id, balance, lifetime_purchased) 
                      VALUES (:user_id, :amount, :amount)
                      ON DUPLICATE KEY UPDATE 
                      balance = balance + :amount2,
                      lifetime_purchased = lifetime_purchased + :amount3";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':amount2', $amount);
            $stmt->bindParam(':amount3', $amount);
            $stmt->execute();
            
            // Record transaction
            $query = "INSERT INTO coin_transactions 
                      (user_id, amount, type, description, reference_type, reference_id, bitcoin_tx_hash, status) 
                      VALUES (:user_id, :amount, :type, :description, :reference_type, :reference_id, :bitcoin_tx_hash, 'completed')";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':reference_type', $reference_type);
            $stmt->bindParam(':reference_id', $reference_id);
            $stmt->bindParam(':bitcoin_tx_hash', $bitcoin_tx_hash);
            $stmt->execute();
            
            $transaction_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return ['success' => true, 'transaction_id' => $transaction_id, 'new_balance' => $this->getBalance($user_id)];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Deduct coins from user account
     */
    public function deductCoins($user_id, $amount, $type = 'spend', $description = '', $reference_type = null, $reference_id = null) {
        try {
            $this->db->beginTransaction();
            
            // Check balance
            $balance = $this->getBalance($user_id);
            if($balance < $amount) {
                throw new Exception('Insufficient coins');
            }
            
            // Update balance
            $query = "UPDATE user_coins SET 
                      balance = balance - :amount,
                      lifetime_spent = lifetime_spent + :amount2
                      WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':amount2', $amount);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Record transaction
            $query = "INSERT INTO coin_transactions 
                      (user_id, amount, type, description, reference_type, reference_id, status) 
                      VALUES (:user_id, :amount, :type, :description, :reference_type, :reference_id, 'completed')";
            $stmt = $this->db->prepare($query);
            $amount_negative = -$amount;
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':amount', $amount_negative);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':reference_type', $reference_type);
            $stmt->bindParam(':reference_id', $reference_id);
            $stmt->execute();
            
            $transaction_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return ['success' => true, 'transaction_id' => $transaction_id, 'new_balance' => $this->getBalance($user_id)];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Transfer coins from one user to another
     */
    public function transferCoins($from_user_id, $to_user_id, $amount, $type = 'tip', $description = '') {
        try {
            $this->db->beginTransaction();
            
            // Deduct from sender
            $deduct = $this->deductCoins($from_user_id, $amount, $type, $description);
            if(!$deduct['success']) {
                throw new Exception($deduct['error']);
            }
            
            // Calculate platform fee
            $platform_fee = $amount * (self::PLATFORM_FEE / 100);
            $creator_amount = $amount - $platform_fee;
            
            // Add to recipient (minus platform fee)
            $query = "INSERT INTO user_coins (user_id, balance, lifetime_earned) 
                      VALUES (:user_id, :amount, :amount)
                      ON DUPLICATE KEY UPDATE 
                      balance = balance + :amount2,
                      lifetime_earned = lifetime_earned + :amount3";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $to_user_id);
            $stmt->bindParam(':amount', $creator_amount);
            $stmt->bindParam(':amount2', $creator_amount);
            $stmt->bindParam(':amount3', $creator_amount);
            $stmt->execute();
            
            // Record earn transaction
            $query = "INSERT INTO coin_transactions 
                      (user_id, amount, type, description, status) 
                      VALUES (:user_id, :amount, 'earn', :description, 'completed')";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $to_user_id);
            $stmt->bindParam(':amount', $creator_amount);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            $this->db->commit();
            
            return ['success' => true, 'platform_fee' => $platform_fee, 'creator_received' => $creator_amount];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($user_id, $limit = 50) {
        $query = "SELECT * FROM coin_transactions 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get coin statistics
     */
    public function getStats($user_id) {
        $query = "SELECT * FROM user_coins WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch();
    }
}