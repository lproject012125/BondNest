<?php

// Read the file content
$content = file_get_contents('profile-page.php');

// Search pattern with the confirm dialog
$search = <<<'EOT'
            } else {
                console.error('Delete post modal not found in the DOM!');
                if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                    // Fallback if modal isn't found
                    deletePost(postId);
                }
            }
EOT;

// Replace with direct function call
$replace = <<<'EOT'
            } else {
                console.error('Delete post modal not found in the DOM!');
                // Removed confirmation dialog, directly call deletePost
                deletePost(postId);
            }
EOT;

// Replace the text
$newContent = str_replace($search, $replace, $content);

// Write the changes back to the file
file_put_contents('profile-page.php', $newContent);

echo "Replacement completed successfully.\n";
?> 