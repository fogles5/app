<?php

class MediaContent {
    private $db;
    private $coinsSystem;
    
    public function __construct($db) {
        $this->db = $db;
        require_once __DIR__ . '/CoinsSystem.php';
        $this->coinsSystem = new CoinsSystem($db);
    }
    
    /**
     * Create new media content
     */
    public function createContent($creator_id, $data, $files) {
        try {
            $this->db->beginTransaction();
            
            // Insert content
            $query = "INSERT INTO media_content 
                      (creator_id, title, description, content_type, price, is_free, is_exclusive, blur_preview, status, published_at) 
                      VALUES (:creator_id, :title, :description, :content_type, :price, :is_free, :is_exclusive, :blur_preview, :status, :published_at)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':creator_id', $creator_id);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':content_type', $data['content_type']);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':is_free', $data['is_free']);
            $stmt->bindParam(':is_exclusive', $data['is_exclusive']);
            $stmt->bindParam(':blur_preview', $data['blur_preview']);
            $status = $data['status'] ?? 'published';
            $stmt->bindParam(':status', $status);
            $published_at = ($status == 'published') ? date('Y-m-d H:i:s') : null;
            $stmt->bindParam(':published_at', $published_at);
            $stmt->execute();
            
            $content_id = $this->db->lastInsertId();
            
            // Upload files
            if(!empty($files)) {
                $this->uploadMediaFiles($content_id, $files);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'content_id' => $content_id];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Upload media files
     */
    private function uploadMediaFiles($content_id, $files) {
        $upload_dir = __DIR__ . '/../uploads/media/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach($files as $index => $file) {
            if(empty($file['tmp_name'])) continue;
            
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            if(move_uploaded_file($file['tmp_name'], $file_path)) {
                $query = "INSERT INTO media_files 
                          (content_id, file_path, file_type, file_size, display_order) 
                          VALUES (:content_id, :file_path, :file_type, :file_size, :display_order)";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':content_id', $content_id);
                $db_path = '/uploads/media/' . $new_filename;
                $stmt->bindParam(':file_path', $db_path);
                $stmt->bindParam(':file_type', $file['type']);
                $stmt->bindParam(':file_size', $file['size']);
                $stmt->bindParam(':display_order', $index);
                $stmt->execute();
            }
        }
    }
    
    /**
     * Get creator's content
     */
    public function getCreatorContent($creator_id, $status = 'published', $limit = 50) {
        $query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM media_purchases WHERE content_id = c.id) as purchases,
                  (SELECT file_path FROM media_files WHERE content_id = c.id ORDER BY display_order LIMIT 1) as thumbnail
                  FROM media_content c
                  WHERE c.creator_id = :creator_id";
        
        if($status) {
            $query .= " AND c.status = :status";
        }
        
        $query .= " ORDER BY c.created_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':creator_id', $creator_id);
        if($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get single content with access check
     */
    public function getContent($content_id, $user_id) {
        $query = "SELECT c.*, 
                  u.username as creator_name,
                  (SELECT COUNT(*) FROM media_purchases WHERE content_id = c.id) as total_purchases,
                  (SELECT COUNT(*) FROM media_likes WHERE content_id = c.id) as total_likes,
                  EXISTS(SELECT 1 FROM media_purchases WHERE content_id = c.id AND buyer_id = :user_id) as user_has_access,
                  EXISTS(SELECT 1 FROM media_likes WHERE content_id = c.id AND user_id = :user_id2) as user_liked
                  FROM media_content c
                  JOIN users u ON u.id = c.creator_id
                  WHERE c.id = :content_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':content_id', $content_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':user_id2', $user_id);
        $stmt->execute();
        
        $content = $stmt->fetch();
        
        if($content) {
            // Get files
            $query = "SELECT * FROM media_files WHERE content_id = :content_id ORDER BY display_order";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->execute();
            $content['files'] = $stmt->fetchAll();
        }
        
        return $content;
    }
    
    /**
     * Purchase content
     */
    public function purchaseContent($content_id, $buyer_id) {
        try {
            $this->db->beginTransaction();
            
            // Get content details
            $query = "SELECT * FROM media_content WHERE id = :content_id AND status = 'published'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->execute();
            $content = $stmt->fetch();
            
            if(!$content) {
                throw new Exception('Content not found');
            }
            
            // Check if already purchased
            $query = "SELECT id FROM media_purchases WHERE content_id = :content_id AND buyer_id = :buyer_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':buyer_id', $buyer_id);
            $stmt->execute();
            
            if($stmt->fetch()) {
                throw new Exception('Already purchased');
            }
            
            // Deduct coins from buyer
            $deduct = $this->coinsSystem->deductCoins(
                $buyer_id, 
                $content['price'], 
                'spend', 
                'Purchased: ' . $content['title'],
                'media_content',
                $content_id
            );
            
            if(!$deduct['success']) {
                throw new Exception($deduct['error']);
            }
            
            // Transfer to creator (with platform fee)
            $transfer = $this->coinsSystem->transferCoins(
                $buyer_id,
                $content['creator_id'],
                $content['price'],
                'earn',
                'Sale: ' . $content['title']
            );
            
            // Record purchase
            $query = "INSERT INTO media_purchases 
                      (content_id, buyer_id, creator_id, price_paid, transaction_id) 
                      VALUES (:content_id, :buyer_id, :creator_id, :price_paid, :transaction_id)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':buyer_id', $buyer_id);
            $stmt->bindParam(':creator_id', $content['creator_id']);
            $stmt->bindParam(':price_paid', $content['price']);
            $stmt->bindParam(':transaction_id', $deduct['transaction_id']);
            $stmt->execute();
            
            // Update content stats
            $query = "UPDATE media_content SET purchase_count = purchase_count + 1 WHERE id = :content_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->execute();
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Content unlocked!'];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get content count for pagination
     */
    public function getContentCount($filters = []) {
        $query = "SELECT COUNT(*) as total
                  FROM media_content c
                  JOIN users u ON u.id = c.creator_id
                  WHERE c.status = 'published'";
        
        if(!empty($filters['creator_id'])) {
            $query .= " AND c.creator_id = :creator_id";
        }
        
        if(!empty($filters['content_type'])) {
            $query .= " AND c.content_type = :content_type";
        }
        
        if(!empty($filters['is_free'])) {
            $query .= " AND c.is_free = 1";
        }
        
        if(!empty($filters['min_price'])) {
            $query .= " AND c.price >= :min_price";
        }
        
        if(!empty($filters['max_price'])) {
            $query .= " AND c.price <= :max_price";
        }
        
        if(!empty($filters['search'])) {
            $query .= " AND (c.title LIKE :search OR c.description LIKE :search OR u.username LIKE :search)";
        }
        
        $stmt = $this->db->prepare($query);
        
        if(!empty($filters['creator_id'])) {
            $stmt->bindParam(':creator_id', $filters['creator_id']);
        }
        if(!empty($filters['content_type'])) {
            $stmt->bindParam(':content_type', $filters['content_type']);
        }
        if(!empty($filters['min_price'])) {
            $stmt->bindParam(':min_price', $filters['min_price']);
        }
        if(!empty($filters['max_price'])) {
            $stmt->bindParam(':max_price', $filters['max_price']);
        }
        if(!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $stmt->bindParam(':search', $search_term);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['total'] ?? 0;
    }
    
    /**
     * Browse all content (marketplace)
     */
    public function browseContent($filters = [], $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT c.*, 
                  u.username as creator_name,
                  u.is_verified,
                  (SELECT file_path FROM media_files WHERE content_id = c.id ORDER BY display_order LIMIT 1) as thumbnail
                  FROM media_content c
                  JOIN users u ON u.id = c.creator_id
                  WHERE c.status = 'published'";
        
        if(!empty($filters['creator_id'])) {
            $query .= " AND c.creator_id = :creator_id";
        }
        
        if(!empty($filters['content_type'])) {
            $query .= " AND c.content_type = :content_type";
        }
        
        if(!empty($filters['is_free'])) {
            $query .= " AND c.is_free = 1";
        }
        
        if(!empty($filters['min_price'])) {
            $query .= " AND c.price >= :min_price";
        }
        
        if(!empty($filters['max_price'])) {
            $query .= " AND c.price <= :max_price";
        }
        
        if(!empty($filters['search'])) {
            $query .= " AND (c.title LIKE :search OR c.description LIKE :search OR u.username LIKE :search)";
        }
        
        $query .= " ORDER BY c.published_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        
        if(!empty($filters['creator_id'])) {
            $stmt->bindParam(':creator_id', $filters['creator_id']);
        }
        if(!empty($filters['content_type'])) {
            $stmt->bindParam(':content_type', $filters['content_type']);
        }
        if(!empty($filters['min_price'])) {
            $stmt->bindParam(':min_price', $filters['min_price']);
        }
        if(!empty($filters['max_price'])) {
            $stmt->bindParam(':max_price', $filters['max_price']);
        }
        if(!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $stmt->bindParam(':search', $search_term);
        }
        
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Like content
     */
    public function likeContent($content_id, $user_id) {
        try {
            $query = "INSERT INTO media_likes (content_id, user_id) VALUES (:content_id, :user_id)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Update count
            $query = "UPDATE media_content SET like_count = like_count + 1 WHERE id = :content_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':content_id', $content_id);
            $stmt->execute();
            
            return ['success' => true];
        } catch(Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Unlike content
     */
    public function unlikeContent($content_id, $user_id) {
        $query = "DELETE FROM media_likes WHERE content_id = :content_id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':content_id', $content_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Update count
        $query = "UPDATE media_content SET like_count = like_count - 1 WHERE id = :content_id AND like_count > 0";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':content_id', $content_id);
        $stmt->execute();
        
        return ['success' => true];
    }
}