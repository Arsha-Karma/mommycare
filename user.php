<?php
include_once 'db.php';



class User {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->conn;
    }

    // Signup method
    public function signup($name, $email, $password, $confirm_password) {
        $errors = [
            "name" => "",
            "email" => "",
            "password" => "",
            "confirm_password" => ""
        ];

        // Validation
        if (empty($name) || strlen($name) < 3) {
            $errors['name'] = "Name must be at least 3 characters.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        }

        if (strlen($password) < 6) {
            $errors['password'] = "Password must be at least 6 characters.";
        }

        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }

        // Check if email or name exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email=? OR name=?");
        $stmt->bind_param("ss", $email, $name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = "Email or Name is already registered.";
        }
        $stmt->close();

        // Check if there are any errors
        $hasError = false;
        foreach ($errors as $err) {
            if ($err !== "") {
                $hasError = true;
                break;
            }
        }

        if (!$hasError) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
            $stmt->bind_param("sss", $name, $email, $hashed);
            if ($stmt->execute()) {
                $stmt->close();
                return ["success" => true, "errors" => [], "message" => "Signup successful! You can login now."];
            } else {
                return ["success" => false, "errors" => [], "message" => "Error: " . $this->db->error];
            }
        } else {
            return ["success" => false, "errors" => $errors, "message" => "Please fix the errors."];
        }
    }

    // Login method
    public function login($identifier, $password) {
        $errors = [
            "identifier" => "",
            "password" => ""
        ];

        if (empty($identifier)) {
            $errors['identifier'] = "Name or Email is required.";
        }

        if (empty($password)) {
            $errors['password'] = "Password is required.";
        }

        if (!empty($errors['identifier']) || !empty($errors['password'])) {
            return ["success" => false, "errors" => $errors, "message" => "Please fix the errors."];
        }

        $stmt = $this->db->prepare("SELECT id, name, password FROM users WHERE email=? OR name=? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $name, $hashed_password);

        if ($stmt->num_rows == 1) {
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                session_start();
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;
                $stmt->close();
                return ["success" => true, "errors" => [], "message" => "Login successful!"];
            } else {
                $errors['password'] = "Incorrect password.";
                return ["success" => false, "errors" => $errors, "message" => "Please fix the errors."];
            }
        } else {
            $errors['identifier'] = "User not found.";
            return ["success" => false, "errors" => $errors, "message" => "Please fix the errors."];
        }
    }
}
?>
