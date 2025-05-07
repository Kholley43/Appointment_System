<?php
/** 
 * Service Model
 *  
 * Handles business logic related to medical services 
 */
class Service {
    private $db;
    
    /**
     * Constructor - initialize with database connection
     * 
     * @param mysqli|PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get services with flexible filtering options
     *
     * @param bool $activeOnly Whether to include only active services
     * @param int $limit Limit the number of services returned (0 for no limit)
     * @param string $orderBy Field to order by (default: 'name')
     * @param array $fields Specific fields to retrieve (empty for all fields)
     * @return array List of services
     */
    public function getServices($activeOnly = true, $limit = 0, $orderBy = 'name', $fields = []) {
        $services = [];
        
        try {
            // Validate orderBy to prevent SQL injection
            $allowedOrderFields = ['name', 'service_id', 'price', 'duration', 'created_at'];
            if (!in_array($orderBy, $allowedOrderFields)) {
                $orderBy = 'name'; // Default to safe value
            }
            
            // Determine fields to select
            $selectedFields = empty($fields) ? '*' : implode(', ', $fields);
            
            // Start building the query
            $query = "SELECT $selectedFields FROM services";
            
            // Add WHERE clause if activeOnly is true
            if ($activeOnly) {
                $query .= " WHERE is_active = 1";
            }
            
            // Add ORDER BY clause
            $query .= " ORDER BY $orderBy";
            
            // Add LIMIT clause if limit is specified
            if ($limit > 0) {
                $query .= " LIMIT ?";
                
                if ($this->db instanceof mysqli) {
                    $stmt = $this->db->prepare($query);
                    $stmt->bind_param("i", $limit);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            // Only enrich data if we're selecting all fields
                            $services[] = ($selectedFields === '*') ? 
                                $this->enrichServiceData($row) : $row;
                        }
                    }
                } elseif ($this->db instanceof PDO) {
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($selectedFields === '*') {
                        $services = array_map([$this, 'enrichServiceData'], $rows);
                    } else {
                        $services = $rows;
                    }
                }
            } else {
                // No limit
                if ($this->db instanceof mysqli) {
                    $result = $this->db->query($query);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            // Only enrich data if we're selecting all fields
                            $services[] = ($selectedFields === '*') ? 
                                $this->enrichServiceData($row) : $row;
                        }
                    }
                } elseif ($this->db instanceof PDO) {
                    $stmt = $this->db->query($query);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($selectedFields === '*') {
                        $services = array_map([$this, 'enrichServiceData'], $rows);
                    } else {
                        $services = $rows;
                    }
                }
            }
            
            error_log("Found " . count($services) . " services");
        } catch (Exception $e) {
            error_log("Error in getServices: " . $e->getMessage());
        }
        
        return $services;
    }
    
    /**
     * Get all active services - WRAPPER for backward compatibility
     *
     * @return array List of services
     */
    public function getAllServices() {
        return $this->getServices(true);
    }
    
    /**
     * Get all services including inactive ones - WRAPPER for backward compatibility
     *
     * @return array List of all services
     */
    public function getAllServicesWithInactive() {
        return $this->getServices(false);
    }
    
    /**
     * Get featured services for homepage display - WRAPPER for backward compatibility
     *
     * @param int $limit Number of services to return
     * @return array List of featured services
     */
    public function getFeaturedServices($limit = 3) {
        return $this->getServices(true, $limit, 'service_id');
    }
    
    /**
     * Get simplified list of active services - WRAPPER for backward compatibility
     *
     * @return array List of active services with basic fields
     */
    public function getServicesBasic() {
        return $this->getServices(true, 0, 'name', ['service_id', 'name', 'description', 'duration', 'price']);
    }
    
    /**
     * Get a single service by ID
     * 
     * @param int $serviceId Service ID
     * @return array|null Service data or null if not found
     */
    public function getServiceById($serviceId) {
        try {
            $query = "SELECT * FROM services WHERE service_id = ?";
            
            if ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $serviceId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $row = $result->fetch_assoc()) {
                    return $this->enrichServiceData($row);
                }
            } elseif ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(1, $serviceId, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    return $this->enrichServiceData($row);
                }
            }
        } catch (Exception $e) {
            error_log("Error in getServiceById: " . $e->getMessage());
        }
        
        return null;
    }
        
    /**
     * Search services by name or description
     * 
     * @param string $searchTerm Term to search for
     * @param bool $activeOnly Whether to include only active services
     * @return array Matching services
     */
    public function searchServices($searchTerm, $activeOnly = true) {
        $services = [];
        
        try {
            $searchTerm = "%$searchTerm%";
            $query = "SELECT * FROM services
                      WHERE (name LIKE ? OR description LIKE ?)";
            
            if ($activeOnly) {
                $query .= " AND is_active = 1";
            }
            
            $query .= " ORDER BY name";
            
            if ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("ss", $searchTerm, $searchTerm);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $services[] = $this->enrichServiceData($row);
                    }
                }
            } elseif ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(1, $searchTerm, PDO::PARAM_STR);
                $stmt->bindParam(2, $searchTerm, PDO::PARAM_STR);
                $stmt->execute();
                
                $services = array_map([$this, 'enrichServiceData'], $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        } catch (Exception $e) {
            error_log("Error in searchServices: " . $e->getMessage());
        }
        
        return $services;
    }
    
    /**
     * Get all services offered by a specific provider
     *
     * @param int $provider_id Provider ID to get services for
     * @return array List of services with provider-specific customizations
     */
    public function getServicesByProvider($provider_id) {
        $services = [];
        
        try {
            // Query that joins provider_services with services table
            // Note the added alias "s.name AS service_name" to match what the view expects
            $query = "SELECT s.*, s.name AS service_name, ps.provider_service_id, ps.custom_duration, ps.custom_notes
                    FROM services s
                    JOIN provider_services ps ON s.service_id = ps.service_id
                    WHERE ps.provider_id = ? AND s.is_active = 1
                    ORDER BY s.name";
            
            if ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $provider_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $services[] = $this->enrichServiceData($row);
                    }
                }
            } elseif ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(1, $provider_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $services = array_map([$this, 'enrichServiceData'], $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        } catch (Exception $e) {
            error_log("Error in getServicesByProvider: " . $e->getMessage());
        }
        
        return $services;
    }
    
    /**
     * Add icon and other metadata to service records
     * 
     * @param array $service Service data row
     * @return array Enhanced service data
     */
    private function enrichServiceData($service) {
        // Add appropriate Font Awesome icon based on service name
        $service['icon'] = $this->getServiceIcon($service['name']);
        
        // Format duration as hours and minutes if needed
        if (isset($service['duration']) && $service['duration'] >= 60) {
            $hours = floor($service['duration'] / 60);
            $minutes = $service['duration'] % 60;
            
            if ($minutes > 0) {
                $service['duration_formatted'] = "{$hours} hour" . ($hours > 1 ? 's' : '') .
                                             " {$minutes} minute" . ($minutes > 1 ? 's' : '');
            } else {
                $service['duration_formatted'] = "{$hours} hour" . ($hours > 1 ? 's' : '');
            }
        } else {
            $service['duration_formatted'] = $service['duration'] . " minute" .
                                          ($service['duration'] != 1 ? 's' : '');
        }
        
        return $service;
    }
    
    /**
     * Get appropriate icon for a service based on its name
     * 
     * @param string $serviceName Name of the service
     * @return string FontAwesome icon name
     */
    private function getServiceIcon($serviceName) {
        $name = strtolower($serviceName);
        
        if (strpos($name, 'check') !== false || strpos($name, 'exam') !== false) {
            return 'stethoscope';
        } elseif (strpos($name, 'therapy') !== false || strpos($name, 'counseling') !== false) {
            return 'brain';
        } elseif (strpos($name, 'cardiac') !== false || strpos($name, 'heart') !== false) {
            return 'heartbeat';
        } elseif (strpos($name, 'dental') !== false || strpos($name, 'teeth') !== false) {
            return 'tooth';
        } elseif (strpos($name, 'eye') !== false || strpos($name, 'vision') !== false) {
            return 'eye';
        } elseif (strpos($name, 'physical') !== false || strpos($name, 'rehab') !== false) {
            return 'dumbbell';
        } elseif (strpos($name, 'vaccine') !== false || strpos($name, 'immun') !== false) {
            return 'syringe';
        } elseif (strpos($name, 'lab') !== false || strpos($name, 'test') !== false) {
            return 'vial';
        } elseif (strpos($name, 'pediatric') !== false || strpos($name, 'child') !== false) {
            return 'child';
        } else {
            // Default icon
            return 'user-md';
        }
    }
    
   /**
     * Create a new service
     *
     * @param array $serviceData Service data to insert
     * @return int|bool New service ID or false on failure
     */
    public function createService($serviceData) {
        try {
            // Validate required fields
            if (empty($serviceData['name'])) {
                error_log("Missing required fields for service creation");
                return false;
            }
                
            // Get provider ID from session
            $providerId = $_SESSION['user_id'] ?? 0;
            
            if (empty($providerId)) {
                error_log("No provider ID found in session");
                return false;
            }
                
            // Set defaults for optional fields
            $duration = $serviceData['duration'] ?? 30;
            $price = $serviceData['price'] ?? 0;
            $isActive = $serviceData['is_active'] ?? 1;
                
            if ($this->db instanceof mysqli) {
                // Begin transaction
                $this->db->begin_transaction();
                
                try {
                    // Insert into services table
                    $sql = "INSERT INTO services (name, description, price, duration) VALUES (?, ?, ?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("ssdi", 
                        $serviceData['name'], 
                        $serviceData['description'], 
                        $price, 
                        $duration
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error inserting service: " . $stmt->error);
                    }
                    
                    $serviceId = $this->db->insert_id;
                    
                    // Associate service with provider
                    $sql2 = "INSERT INTO provider_services (provider_id, service_id) VALUES (?, ?)";
                    $stmt2 = $this->db->prepare($sql2);
                    $stmt2->bind_param("ii", $providerId, $serviceId);
                    
                    if (!$stmt2->execute()) {
                        throw new Exception("Error associating service with provider: " . $stmt2->error);
                    }
                    
                    // Commit transaction
                    $this->db->commit();
                    return $serviceId;
                    
                } catch (Exception $e) {
                    // Rollback on error
                    $this->db->rollback();
                    error_log("Transaction failed: " . $e->getMessage());
                    return false;
                }
                
            } elseif ($this->db instanceof PDO) {
                // Begin transaction
                $this->db->beginTransaction();
                
                try {
                    // Insert into services table
                    $sql = "INSERT INTO services (name, description, price, duration) VALUES (:name, :description, :price, :duration)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindParam(':name', $serviceData['name']);
                    $stmt->bindParam(':description', $serviceData['description']);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':duration', $duration);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error inserting service: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    $serviceId = $this->db->lastInsertId();
                    
                    // Associate service with provider
                    $sql2 = "INSERT INTO provider_services (provider_id, service_id) VALUES (:provider_id, :service_id)";
                    $stmt2 = $this->db->prepare($sql2);
                    $stmt2->bindParam(':provider_id', $providerId);
                    $stmt2->bindParam(':service_id', $serviceId);
                    
                    if (!$stmt2->execute()) {
                        throw new Exception("Error associating service with provider: " . implode(", ", $stmt2->errorInfo()));
                    }
                    
                    // Commit transaction
                    $this->db->commit();
                    return $serviceId;
                    
                } catch (Exception $e) {
                    // Rollback on error
                    $this->db->rollback();
                    error_log("Transaction failed: " . $e->getMessage());
                    return false;
                }
            }
        } catch (Exception $e) {
            error_log("Exception in createService: " . $e->getMessage());
        }
            
        return false;
    }

    /**
     * Get the total count of services
     * @param bool $activeOnly Whether to count only active services
     * @return int Total number of services
     */
    public function getTotalCount($activeOnly = false) {
        try {
            $query = "SELECT COUNT(*) as count FROM services";
            if ($activeOnly) {
                $query .= " WHERE is_active = 1";
            }
            
            $stmt = $this->db->query($query);
            if ($stmt) {
                                $result = $stmt->fetch_assoc();
                return $result['count'];
            }
        } catch (Exception $e) {
            error_log("Error in getTotalCount: " . $e->getMessage());
        }
        return 0;
    }

    /**
     * Update an existing service
     * 
     * @param int $serviceId Service ID to update
     * @param array $serviceData Updated service data
     * @return bool Success flag
     */
    public function updateService($serviceId, $serviceData) {
        try {
            // Validate service ID
            if (empty($serviceId)) {
                error_log("Missing service ID for update");
                return false;
            }
            
            // Build query dynamically based on provided fields
            $updateFields = [];
            $params = [];
            $types = '';
            
            // Check each possible field and add to update if present
            if (isset($serviceData['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $serviceData['name'];
                $types .= 's';
            }
            
            if (isset($serviceData['description'])) {
                $updateFields[] = "description = ?";
                $params[] = $serviceData['description'];
                $types .= 's';
            }
            
            if (isset($serviceData['duration'])) {
                $updateFields[] = "duration = ?";
                $params[] = $serviceData['duration'];
                $types .= 'i';
            }
            
            if (isset($serviceData['price'])) {
                $updateFields[] = "price = ?";
                $params[] = $serviceData['price'];
                $types .= 'd';
            }
            
            if (isset($serviceData['is_active'])) {
                $updateFields[] = "is_active = ?";
                $params[] = $serviceData['is_active'];
                $types .= 'i';
            }
            
            // If no fields to update, return false
            if (empty($updateFields)) {
                error_log("No fields provided for service update");
                return false;
            }
            
            // Add service ID to params
            $params[] = $serviceId;
            $types .= 'i';
            
            $query = "UPDATE services SET " . implode(", ", $updateFields) . " WHERE service_id = ?";
            
            if ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                
                // Dynamically bind parameters
                $bindParams = array_merge([$types], $params);
                $stmt->bind_param(...$bindParams);
                
                return $stmt->execute();
            } elseif ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                
                // Bind each parameter with its appropriate type
                foreach ($params as $i => $param) {
                    $paramType = PDO::PARAM_STR;
                    if ($types[$i] === 'i') {
                        $paramType = PDO::PARAM_INT;
                    } elseif ($types[$i] === 'd') {
                        $paramType = PDO::PARAM_STR; // PDO doesn't have PARAM_FLOAT
                    }
                    $stmt->bindValue($i + 1, $param, $paramType);
                }
                
                return $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error in updateService: " . $e->getMessage());
        }
        
        return false;
    }
        
    /**
     * Delete a service (or deactivate it)
     * 
     * @param int $serviceId Service ID to delete
     * @param bool $permanent Whether to permanently delete or just deactivate
     * @return bool Success flag
     */
    public function deleteService($serviceId, $permanent = false) {
        try {
            // Check if service is in use
            $query = "SELECT COUNT(*) as count FROM appointments WHERE service_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            // If service is in use and permanent deletion requested, don't allow it
            if ($permanent && $row['count'] > 0) {
                error_log("Cannot permanently delete service ID $serviceId - it is used in appointments");
                return false;
            }
            
            if ($permanent) {
                $query = "DELETE FROM services WHERE service_id = ?";
            } else {
                $query = "UPDATE services SET is_active = 0 WHERE service_id = ?";
            }
            
            if ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("i", $serviceId);
                return $stmt->execute();
            } elseif ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(1, $serviceId, PDO::PARAM_INT);
                return $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error in deleteService: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Add a service for a provider
     * 
     * @param int $provider_id Provider ID
     * @param string $name Service name
     * @param string $description Service description
     * @param float $price Service price
     * @return bool Success flag
     */
    public function addService($provider_id, $name, $description, $price) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO provider_services (provider_id, name, description, price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("issd", $provider_id, $name, $description, $price);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error adding service: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get top services by usage
     * @param int $limit Number of services to return
     * @return array Top services with usage counts
     */
    public function getTopServicesByUsage($limit = 5) {
        try {
            $query = "SELECT s.service_id, s.name, COUNT(a.appointment_id) as usage_count
                    FROM services s
                    LEFT JOIN appointments a ON s.service_id = a.service_id
                    GROUP BY s.service_id, s.name
                    ORDER BY usage_count DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Database error in getTopServicesByUsage: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Toggle service active status
     * @param int $serviceId Service ID to toggle
     * @return bool Success flag
     */
    public function toggleServiceStatus($serviceId) {
        try {
            // First get current status
            $stmt = $this->db->prepare("SELECT is_active FROM services WHERE service_id = ?");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                $newStatus = $row['is_active'] ? 0 : 1; // Toggle status
                
                // Update status
                $updateStmt = $this->db->prepare("UPDATE services SET is_active = ? WHERE service_id = ?");
                $updateStmt->bind_param("ii", $newStatus, $serviceId);
                return $updateStmt->execute();
            }
            return false;
        } catch (Exception $e) {
            error_log("Error toggling service status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a service exists
     * @param int $serviceId Service ID to check
     * @return bool Whether the service exists
     */
    public function serviceExists($serviceId) {
        try {
            $stmt = $this->db->prepare("SELECT service_id FROM services WHERE service_id = ?");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Error checking if service exists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get services by category
     * @param string $category Category name
     * @return array Services in the category
     */
    public function getServicesByCategory($category) {
        $services = [];
        
        try {
            $query = "SELECT s.* FROM services s
                     JOIN service_categories sc ON s.service_id = sc.service_id
                     JOIN categories c ON sc.category_id = c.category_id
                     WHERE c.name = ? AND s.is_active = 1
                     ORDER BY s.name";
            
            if ($this->db instanceof mysqli) {
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("s", $category);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $services[] = $this->enrichServiceData($row);
                    }
                }
            } elseif ($this->db instanceof PDO) {
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(1, $category, PDO::PARAM_STR);
                $stmt->execute();
                
                $services = array_map([$this, 'enrichServiceData'], $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        } catch (Exception $e) {
            error_log("Error in getServicesByCategory: " . $e->getMessage());
        }
        
        return $services;
    }
}
