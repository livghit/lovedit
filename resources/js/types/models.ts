export interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
}

export interface Review {
    id: string | number;
    book_id: string | number;
    rating: number;
    content: string;
    created_at: string;
    book: Book;
}

export interface ToReviewItem {
    id: string | number;
    book_id: string | number;
    added_at: string;
    book: Book;
}
