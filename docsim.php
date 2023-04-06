<?php

class DocSim {
    private function tokenize($text) {
        $text = strtolower($text);
        preg_match_all('/\b\w+\b/', $text, $matches);
        return $matches[0];
    }

    private function countWords($words) {
        $word_count = array();
        foreach ($words as $word) {
            if (isset($word_count[$word])) {
                $word_count[$word]++;
            } else {
                $word_count[$word] = 1;
            }
        }
        return $word_count;
    }

    public function rateText($keywords, $text) {
        $text = strtolower($text);
        $words = $this->tokenize($text);
        $word_count = $this->countWords($words);

        $cumulative_frequency = 0;
        foreach ($keywords as $keyword) {
            $keyword = strtolower($keyword);
            if (isset($word_count[$keyword])) {
                $cumulative_frequency += $word_count[$keyword];
            }
        }

        $max_cumulative_frequency = count($words);

        $normalized_rating = $max_cumulative_frequency > 0 ? $cumulative_frequency / $max_cumulative_frequency : 0;

        $scaled_rating = $normalized_rating * 5;

        return $scaled_rating;
    }
}




