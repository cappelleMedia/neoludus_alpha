<?php

/**
 * DaoObject
 * This is an interface for all classes that handle user database functionality
 * @package dao
 * @subpackage dao.user
 * @author Jens Cappelle <cappelle.design@gmail.com>
 */
interface userdao {

    public function getUsers();
    
    public function getSimple($user_id);

    public function updatePw($user_id, $pw_old, $pw_new);

    public function updateUserUserRole($userId, $userRoleId);

    public function updateUserAvatar($userId, $avatarId);

    //FIXME allow this?
//    public function updateUserName($userId, $username);

    public function updateUserDonated($userId, $donated);

    public function updateUserMail($userId, $newEmail);

    public function updateUserKarma($userId, $newAmount);

    public function updateUserRegKey($userId, $regKey);

    public function updateUserWarnings($userId, $warnings);

    public function updateUserDiamonds($userId, $diamonds);

    public function updateUserDateTimePref($userId, $dateTimePref);

    public function updateUserLastLogin($userId, $lastLogin);

    public function updateUserActiveTime($userId, $activeTime);

//    public function updateUserLastComment($userId, $commentId);

    public function addNotification($userId, Notification $notification);

    public function updateNotification($userId, $notificationId, $isRead);

    public function removeNotification($userId, $notificationId);

    public function addAchievement($userId, $achievementId);
}