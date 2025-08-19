<?php
namespace App\Controllers;

use App\Core\View;

class HomeController
{
    public function index(): void { View::render('pages/home.php'); }
    public function contests(): void { View::render('pages/contests.php'); }
    public function rankings(): void { View::render('pages/rankings.php'); }
    public function contestDetail(int $id): void {
        if (empty($_SESSION['user'])) { View::render('pages/require_login.php', ['redirect' => "/contests/{$id}"]); return; }
        View::render('pages/contest_detail.php', ['contestId' => $id]);
    }
}
