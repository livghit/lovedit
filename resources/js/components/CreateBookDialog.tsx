import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';

interface CreateBookDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess?: () => void;
}

export default function CreateBookDialog({
    open,
    onOpenChange,
    onSuccess,
}: CreateBookDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Book Manually</DialogTitle>
                    <DialogDescription>
                        Create a book entry manually. Only title is required.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action="/books/manual"
                    method="post"
                    onSuccess={() => {
                        onOpenChange(false);
                        onSuccess?.();
                    }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {/* Title (Required) */}
                            <div className="space-y-2">
                                <Label htmlFor="title">
                                    Title{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    name="title"
                                    placeholder="Enter book title"
                                    required
                                    autoFocus
                                />
                                {errors.title && (
                                    <p className="text-sm text-destructive">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            {/* Author (Optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="author">Author</Label>
                                <Input
                                    id="author"
                                    name="author"
                                    placeholder="Enter author name"
                                />
                                {errors.author && (
                                    <p className="text-sm text-destructive">
                                        {errors.author}
                                    </p>
                                )}
                            </div>

                            {/* ISBN (Optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="isbn">ISBN</Label>
                                <Input
                                    id="isbn"
                                    name="isbn"
                                    placeholder="Enter ISBN"
                                />
                                {errors.isbn && (
                                    <p className="text-sm text-destructive">
                                        {errors.isbn}
                                    </p>
                                )}
                            </div>

                            {/* Cover URL (Optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="cover_url">
                                    Cover Image URL
                                </Label>
                                <Input
                                    id="cover_url"
                                    name="cover_url"
                                    type="url"
                                    placeholder="https://example.com/cover.jpg"
                                />
                                {errors.cover_url && (
                                    <p className="text-sm text-destructive">
                                        {errors.cover_url}
                                    </p>
                                )}
                            </div>

                            {/* Publisher (Optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="publisher">Publisher</Label>
                                <Input
                                    id="publisher"
                                    name="publisher"
                                    placeholder="Enter publisher name"
                                />
                                {errors.publisher && (
                                    <p className="text-sm text-destructive">
                                        {errors.publisher}
                                    </p>
                                )}
                            </div>

                            {/* Publish Date (Optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="publish_date">
                                    Publish Year
                                </Label>
                                <Input
                                    id="publish_date"
                                    name="publish_date"
                                    type="number"
                                    min="1000"
                                    max={new Date().getFullYear() + 1}
                                    placeholder="e.g., 2023"
                                />
                                {errors.publish_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.publish_date}
                                    </p>
                                )}
                            </div>

                            {/* Description (Optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    placeholder="Enter book description"
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            {/* Actions */}
                            <div className="flex justify-end gap-3 pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Book'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
