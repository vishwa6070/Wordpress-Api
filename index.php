<?php

/*
Plugin Name: REST API
Description: This is custom  Rest Api Plugin for learning purpose.
Version: 1.0.0
Author: vishwakarma
*/

use Firebase\JWT\JWT;

define('SITE_URL', site_url());
require_once(ABSPATH . "wp-admin" . '/includes/image.php');
require_once(ABSPATH . "wp-admin" . '/includes/file.php');
require_once(ABSPATH . "wp-admin" . '/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/user.php');

class REST_APIS extends WP_REST_Controller
{
    private $api_namespace;
    private $api_version;
    public $user_token;
    public $user_id;

    public function __construct()
    {
        $this->api_namespace = "api/v";
        $this->api_version = "1";
        $this->init();

        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                $this->user_token = $matches[1];
            }
        }
    }

    //Start successResponse message.
    private function successResponse($message = '', $data = array(), $total = array())
    {
        $response = array();
        $response['status'] = "Success";
        $response['error_type'] = "";
        $response['message'] = $message;
        $response['data'] = $data;
        if (!empty($total)) {
            $response['pagination'] = $total;
        }
        return new WP_REST_Response($response, 200);
    }
    //End succcessResponse message.

    //Start errorResponse message.
    private function errorResponse($message = '', $type = 'ERROR')
    {
        $response = array();
        $response['status'] = "failed";
        $response['error_type'] = $type;
        $response['message'] = $message;
        $response['data'] = array();
        return new WP_REST_Response($response, 400);
    }
    //End errorResponse message.

    //Register routes get,post .
    public function register_routes()
    {
        $namespace = $this->api_namespace . $this->api_version;
        $privateItems = array('get_profile_by_id', 'updateUserProfile');
        $getItems = array('get_about_us', 'get_profile', 'getUsersByRollListing');
        $publicItems = array('user_registration', 'update_new_password', 'get_contact_us', 'get_gallery', 'deleteUserbyId', 'create_post', 'getAllpost', 'Update_post', 'getUserbyId','delete_post');

        foreach ($privateItems as $Items) {
            register_rest_route($namespace, "/" . $Items, array(
                array(
                    "methods" => "POST",
                    "callback" => array($this, $Items),
                    "permission_callback" => !empty($this->user_token) ? '__return_true' : '__return_false'
                ),
            ));
        }

        foreach ($getItems as $Items) {
            register_rest_route($namespace, "/" . $Items, array(
                array(
                    "methods" => "GET",
                    "callback" => array($this, $Items)
                ),
            ));
        }

        foreach ($publicItems as $Items) {
            register_rest_route($namespace, "/" . $Items, array(
                array(
                    "methods" => "POST",
                    "callback" => array($this, $Items)
                ),
            ));
        }
    }

    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', function () {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function ($value) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: POST,GET,PUT,OPTIONS,DELETE');
                header('Access-Control-Allow-Credentials: true');
                return $value;
            });
        }, 15);
    }

    //User is alraed exists.
    public function isUserExists($user)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $user));
        if ($count == 1) {
            return true;
        } else {
            return false;
        }
    }

    //Create get user id by token.
    public function getUserIdByToken($token)
    {
        $decoded_array = array();
        if ($token) {
            try {
                $decoded = JWT::decode($token, JWT_AUTH_SECRET_KEY, array('HS256'));
                $decoded_array = (array) $decoded;
            } catch (\Firebase\JWT\ExpiredException $e) {
                return false;
            }
        }
        if (count($decoded_array) > 0) {
            $user_id = $decoded_array['data']->user->id;
        }
        if ($this->isUserExists($user_id)) {
            return $user_id;
        } else {
            return false;
        }
    }

    //Check JWT auth attechment.
    function jwt_auth($data, $user)
    {
        unset($data['user_nicename']);
        unset($data['user_display_name']);
        $site_url = site_url();
        $result = $this->get_profile($user->ID);
        $result['token'] = $data['token'];
        return $this->successResponse('User Logged in successfully', $result);
    }

    // Check valid id token.
    private function isValidToken()
    {
        $this->user_id = $this->getUserIdByToken($this->user_token);
    }

    // Get profile by id.
    public function get_profile_by_id($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $this->isValidToken();
        $user_id = isset($this->user_id) ? $this->user_id : $param['user_id'];
        if (empty($user_id)) {
            return $this->errorResponse('Please Enter Valid Token.');
        } else {
            $userInfo = get_userdata($user_id);
            $user_email = $userInfo->user_email;
            $f_name = get_user_meta($user_id, "first_name", true);
            $l_name = get_user_meta($user_id, "last_name", true);
            $first_name = isset($f_name) ? $f_name : "";
            $last_name = isset($l_name) ? $l_name : "";
            $full_name = $first_name . " " . $last_name;
            $user_array[] = array(
                "id" => $user_id,
                "email" => $user_email,
                "name" => $full_name
            );
            return $this->successResponse('User Get Successfully.', $user_array);
        }
    }

    //Get profile with image.
    public function get_profile($user_id)
    {
        global $wpdb;
        $userInfo = get_user_by('ID', $user_id);
        $first_name = get_user_meta($user_id, "first_name", true);
        $last_name = get_user_meta($user_id, "last_name", true);
        $f_name = !empty($first_name) ? $first_name : "";
        $l_name = !empty($last_name) ? $last_name : "";
        $full_name = $f_name . " " . $l_name;
        $dob = get_user_meta($user_id, "dob", true);
        $contact = get_user_meta($user_id, "contact", true);
        $profile_pic = get_user_meta($user_id, "profile_pic", true);
        $thumbnailImgUrl = get_post_meta($profile_pic, '_wp_attached_file', true);
        if (empty($profile_pic)) {
            $patient_img = 'https://gravatar.com/avatar/dba6bae8c566f9d4041fb9cd9ada7741?d=identicon&f=y';
        } else {
            $patient_img = SITE_URL . '/wp-content/uploads/' . $thumbnailImgUrl;
        }

        $result = array(
            "user_id" => $user_id,
            "user_email" => $userInfo->user_email,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "full_name" => $full_name,
            "dob" => $dob,
            "contact" => $contact,
            "profile_pic" => $patient_img
        );
        print_r('$result');
        die();
        if (!empty($userInfo)) {
            return $result;
        } else {
            return 0;
        }
    }

    // User Registration.
    public function user_registration($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $user_name = trim($param['username']);
        $email = trim($param['email']);
        $password = trim($param['password']);
        $first_name = trim($param['firstname']);
        $last_name = trim($param['lastname']);
        $dob = trim($param['dob']);
        $contact = trim($param['contact']);

        if (email_exists(($email))) {
            return $this->errorResponse('Email already exists.');
        } else {
            $user_id = wp_create_user($user_name, $password, $email);
            if ($user_id) {
                update_user_meta($user_id, "first_name", $param['firstname']);
                update_user_meta($user_id, "last_name", $param['lastname']);
                update_user_meta($user_id, "dob", $param['dob']);
                update_user_meta($user_id, "contact", $param['contact']);
                $data = $this->get_profile($user_id);
                return $this->successResponse('User created successfully.', $data);
            }
        }
    }

    //Update user profile.
    public function updateUserProfile($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $this->isValidToken();
        $user_id = isset($this->user_id) ? $this->user_id : $param['user_id'];
        if (empty($user_id)) {
            return $this->errorResponse('Please Enter Valid Token.');
        } else {
            !empty($param['first_name']) ? update_user_meta($user_id, 'first_name', $param['first_name']) : '';
            !empty($param['last_name']) ? update_user_meta($user_id, 'last_name', $param['last_name']) : '';
            !empty($param['full_name']) ? update_user_meta($user_id, 'full_name', $param['full_name']) : '';
            !empty($param['dob']) ? update_user_meta($user_id, 'dob', $param['dob']) : '';
            !empty($param['contact']) ? update_user_meta($user_id, 'contact', $param['contact']) : '';
            if (!empty($_FILES['profile_pic'])) {
                $userProfileImgId = media_handle_upload('profile_pic', $user_id);
                update_user_meta($user_id, 'profile_pic', $userProfileImgId);
            }
            $data = $this->get_profile($user_id);
            return $this->successResponse('User profile updated successfully', $data);
        }
    }

    //Function for change password
    public function update_new_password($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $user_data = get_user_by('email', trim($param['user_email']));
        $user_id = $user_data->ID;
        $current_pass = $param['oldPassword'];
        $new_pass = $param['new_password'];
        $con_pass = $param['con_password'];
        $user = get_userdata($user_id);
        if (empty($user_id)) {
            // return $this->errorResponse('Please enter the valid token.');
            if ($new_pass === $con_pass) {
            }
        } elseif (!wp_check_password($current_pass, $user->data->user_pass, $user->ID)) {
            return $this->errorResponse('Incorrect current password.');
        } elseif ($new_pass != $con_pass) {
            return $this->errorResponse('Password does not match. Please try again!');
        } else {
            $udata['ID'] = $user->data->ID;
            $udata['user_pass'] = $new_pass;
            $uid = wp_update_user($udata);
            if ($uid) {
                return $this->errorResponse('User not found.');
            } else {
                return $this->errorResponse('An un-expected error');
            }
        }

        // $user_id   = $param['user_id'];
        // $pass = $param['password'];
        // $confirm_pass = $param['confirmPassword'];
        // $user_info = get_user_by('ID', $user_id);
        // $user_id = $user_info->ID;
        // if ($user_id) {
        //     if ($pass === $confirm_pass) {
        //         wp_set_password($pass, $user_id);
        //         return $this->successResponse('Your Password has been changed!');
        //     } else {
        //         return $this->errorResponse('Password does not match. Please try again!');
        //     }
        // } else {
        //     return $this->errorResponse('User not found.');
        // }
    }

    //Get user by roll listing
    public function getUsersByRollListing($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $args = array(
            'role'    => 'Subscriber',
            'orderby' => 'user_nicename',
            'order'   => 'ASC'
        );
        $users = get_users($args);
        $res = array();
        foreach ($users as $user) {
            $userDta = get_user_meta($user->ID);
            $res['id'] = $user->ID;
            $res['user_login'] = $user->user_login;
            $res['user_email'] = $user->user_email;
            $res['first_name'] = !empty($userDta['first_name'][0]) ? $userDta['first_name'][0] : '';
            $res['last_name'] = !empty($userDta['last_name'][0]) ? $userDta['last_name'][0] : '';
            $res['dob'] = !empty($userDta['dob'][0]) ? $userDta['dob'][0] : '';
            $res['phone_numer'] = !empty($userDta['contact'][0]) ? $userDta['contact'][0] : '';
            $dta[] = $res;
        }
        return $this->successResponse('', $dta);
    }

    // Get user by user token id.
    public function getUserbyId($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $user_id = $param['user_id'];
        // $this->isValidToken();
        // $user_id = isset($this->user_id) ? $this->user_id : $param['user_id'];
        $result = $this->get_profile($user_id);
        return $this->successResponse('User get successfully.', $result);
    }

    // Delete user by token id.
    public function deleteUserbyId($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $this->isValidToken();
        $user_id = isset($this->user_id) ? $this->user_id : $param['user_id'];
        if ($user_id) {
            $del = wp_delete_user($user_id);
            if ($del) {
                return $this->successResponse('User deleted successfully.');
            }
        } else {
            return $this->errorResponse('user not found.');
        }
    }

    // Create post.
    public function create_post($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $title = $param['title'];
        $content = $param['content'];
        $description = $param['description'];
        $author = $param['author'];
        $price = $param['price'];
        $post_id = wp_insert_post(array(
            "post_title" => $title,
            "post_content" => $content,
            "post_status" => 'publish',
            "post_type" => 'books',
        ));
        if ($post_id) {
            add_post_meta($post_id, "description", $description);
            add_post_meta($post_id, "author", $author);
            add_post_meta($post_id, "price", $price);
        }
        // wp_set_post_categories($post_id, '3');
        if ($_FILES['image']) {
            foreach ($_FILES as $file => $array) {
                $attach_id = media_handle_upload($file, $post_id);
            }
        }
        if ($attach_id > 0) {
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
        }
        return $this->successResponse('Post Created Successfully.');
    }

    public function getAllpost($request)
    {
        global $wpdb;
        $param = $request->get_params();
        // $user_id = get_the_ID('ID');
        $args = array(
            "post_type" => "books",
            "post_status" => "publish"
        );
        $data =  new WP_Query($args);
        $postData = $data->posts;
        $post_array = array();
        foreach ($postData as $post) {
            $post_id = $post->ID;
            $postDta = get_post_meta($post_id);
            $postImgId = $postDta['_thumbnail_id'][0];
            $postImgUrl = get_post_meta($postImgId);
            $featureImg = $postImgUrl['_wp_attached_file'][0];
            if (!empty($postImgId)) {
                $imgUrl = "http://localhost/project/wordpress_project/wordpress_1/wp-content/uploads/" . $featureImg . "";
            } else {
                $imgUrl = "";
            }
            $post_title = $post->post_title;
            $post_content = $post->post_content;
            $description = get_post_meta($post_id, "description", true);
            $author = get_post_meta($post_id, "author", true);
            $price = get_post_meta($post_id, "price", true);
            $post_array[] = array(
                "post_id" => $post_id,
                "post_title" => $post_title,
                "post_content" => $post_content,
                "description" => $description,
                "author" => $author,
                "price" => $price,
                "Image" => $imgUrl
            );
        }
        return $this->successResponse('Post get successfully.', $post_array);
    }

    public function Update_post($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $post_id = $param['post_id'];
       
        $title = $param['title'];
        $content = $param['content'];
        $descriptiion = $param['description'];
        $author = $param['author'];
        $price = $param['price'];
        $p_id = wp_update_post(array(
            'ID' => $post_id,
            "post_title" => $title,
            "post_content" => $content,
            "post_status" => 'publish',
            "post_type" => 'books',
            'meta_input' => array(
                'description' => $descriptiion,
                'author' => $author,
                'price' => $price
               )
        ));
        if ($_FILES['image']) {
            foreach ($_FILES as $file => $array) {
                $attach_id = media_handle_upload($file, $p_id);
            }
        }
        if ($attach_id > 0) {
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
        }
        return $this->successResponse('Post Created Successfully.');
    }

    public function delete_post($request){
        global$wpdb;
        $param = $request->get_params();
        $user_id = $param['user_id'];
        if ($user_id) {
            $delete = wp_delete_post($user_id);
            if ($delete) {
                return $this->successResponse('User deleted successfully.');
            }
        } else {
            return $this->errorResponse('user not found.');
        }
	
	        
    }

    //Create aobut us page.
    public function get_about_us($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $page_data = $wpdb->get_row("SELECT `post_title`,`post_content` FROM `wp_posts` WHERE `post_name`='about-us'", ARRAY_A);
        $result['post_title'] = $page_data['post_title'];
        $result['post_content'] = $page_data['post_content'];
        return $this->successResponse('About us data retrieve successfully.', $result);
    }

    public function get_contact_us($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $page_data = $wpdb->get_row("SELECT `post_title`,`post_content` FROM `wp_posts` WHERE `post_name`='contact-us'", ARRAY_A);
        $result['post_title'] = $page_data['post_title'];
        $result['post_content'] = $page_data['post_content'];
        return $this->successResponse('Contact us data retrieve successfully.', $result);
    }

    public function get_gallery($request)
    {
        global $wpdb;
        $param = $request->get_params();
        $page_data = $wpdb->get_row("SELECT `post_title`,`post_content` FROM `wp_posts` WHERE `post_name`='gallery'", ARRAY_A);
        $result['post_title'] = $page_data['post_title'];
        $result['post_content'] = $page_data['post_content'];
        return $this->successResponse('Gallery data get successfully', $result);
    }
}
$serverApi = new REST_APIS();
$serverApi->init();
add_filter('jwt_auth_token_before_dispatch', array($serverApi, 'jwt_auth'), 10, 2);

