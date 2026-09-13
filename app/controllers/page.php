<?php
// path: /app/controllers/page.php

require_once APPROOT . '/core/pages.php';

/* [AI:GPT-5.6 Sol | 2026-09-13 03:24:00 UTC] */
class page extends controller
{
    // Designation as a Core Module prevents deletion from the site/DB
    public static $is_core = true;

    /**
     * Render one published filesystem-backed page.
     *
     * @param string|array<int, string> $slug Requested page slug.
     *
     * @return void
     */
    public function index($slug = 'home'): void
    {
        $pageSlug = is_array($slug)
            ? (string) ($slug[0] ?? 'home')
            : (string) $slug;

        $pageSlug = pages::normalizeSlug($pageSlug);

        if (!pages::validSlug($pageSlug)) {
            (new error_handler())->not_found();
            return;
        }

        $page = pages::get($pageSlug, true);

        if ($page === null) {
            (new error_handler())->not_found();
            return;
        }

        $this->view('pages/dynamic', $page);
    }

    /**
     * Core Pages administration.
     *
     * GET:
     * - Lists discovered pages.
     * - Loads one page for editing when ?edit={slug} is supplied.
     *
     * POST:
     * - create
     * - update
     * - delete
     *
     * @param array<int, string> $params Admin route parameters.
     *
     * @return void
     */
    public function admin($params = []): void
    {
        $this->require_admin(7);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'create') {
                pages::create([
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'title' => (string) ($_POST['title'] ?? ''),
                    'description' => (string) ($_POST['description'] ?? ''),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'content_file' => (string) (
                        $_POST['content_file'] ?? 'main.md'
                    ),
                    'content' => (string) ($_POST['content'] ?? ''),
                ]);

                header('Location: /admin/page');
                exit;
            }

            if ($action === 'update') {
                $originalSlug = pages::normalizeSlug(
                    (string) ($_POST['original_slug'] ?? '')
                );

                if (!pages::validSlug($originalSlug)) {
                    http_response_code(400);
                    $this->error_page('Invalid original page slug.');
                    return;
                }

                $updatedPage = pages::update(
                    $originalSlug,
                    [
                        'slug' => (string) ($_POST['slug'] ?? ''),
                        'title' => (string) ($_POST['title'] ?? ''),
                        'description' => (string) (
                            $_POST['description'] ?? ''
                        ),
                        'status' => (string) ($_POST['status'] ?? 'draft'),
                        'content_file' => (string) (
                            $_POST['content_file'] ?? 'main.md'
                        ),
                        'content' => (string) ($_POST['content'] ?? ''),
                    ]
                );

                header(
                    'Location: /admin/page?edit='
                    . rawurlencode((string) $updatedPage['slug'])
                );
                exit;
            }

            if ($action === 'delete') {
                $slug = pages::normalizeSlug(
                    (string) ($_POST['slug'] ?? '')
                );

                if (!pages::validSlug($slug)) {
                    http_response_code(400);
                    $this->error_page('Invalid page slug.');
                    return;
                }

                pages::delete($slug);

                header('Location: /admin/page');
                exit;
            }

            http_response_code(400);
            $this->error_page('Invalid Pages action.');
            return;
        }

        $editPage = null;
        $editSlug = pages::normalizeSlug(
            (string) ($_GET['edit'] ?? '')
        );

        if ($editSlug !== '') {
            if (!pages::validSlug($editSlug)) {
                (new error_handler())->not_found();
                return;
            }

            $editPage = pages::get($editSlug, false);

            if ($editPage === null) {
                (new error_handler())->not_found();
                return;
            }
        }

        $this->view('admin/pages', [
            'pages' => pages::all(),
            'page' => $editPage,
        ]);
    }
}
/* [End AI:GPT-5.6 Sol] */
