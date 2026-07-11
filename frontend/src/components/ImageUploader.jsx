import { useState, useRef } from 'react';
import { Upload, X, Image as ImageIcon } from 'lucide-react';
import { uploadFile, getImageUrl } from '@/lib/api';
import toast from 'react-hot-toast';

export default function ImageUploader({ type, id, onUploaded, multiple = true }) {
  const [uploading, setUploading] = useState(false);
  const inputRef = useRef();

  const handleFiles = async (files) => {
    if (!id) {
      toast.error('Save the record first before uploading images');
      return;
    }
    setUploading(true);
    try {
      for (const file of files) {
        const result = await uploadFile(type, id, file);
        onUploaded?.(result);
      }
      toast.success('Image uploaded');
    } catch {
      toast.error('Upload failed');
    } finally {
      setUploading(false);
    }
  };

  return (
    <div
      className="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-brand-400 transition-colors cursor-pointer"
      onClick={() => inputRef.current?.click()}
      onDragOver={(e) => e.preventDefault()}
      onDrop={(e) => { e.preventDefault(); handleFiles(e.dataTransfer.files); }}
    >
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        multiple={multiple}
        className="hidden"
        onChange={(e) => handleFiles(e.target.files)}
      />
      <Upload className="w-8 h-8 text-gray-400 mx-auto mb-2" />
      <p className="text-sm text-gray-600">
        {uploading ? 'Uploading...' : 'Drag & drop images or click to browse'}
      </p>
    </div>
  );
}

export function ImageGallery({ images, onDelete }) {
  if (!images?.length) {
    return (
      <div className="text-center py-8 text-gray-400">
        <ImageIcon className="w-12 h-12 mx-auto mb-2 opacity-50" />
        <p className="text-sm">No images yet</p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
      {images.map((img) => (
        <div key={img.id} className="relative group aspect-square rounded-lg overflow-hidden bg-gray-100">
          <img
            src={getImageUrl(img.thumbnail_path || img.file_path)}
            alt={img.caption || ''}
            className="w-full h-full object-cover"
          />
          {onDelete && (
            <button
              className="absolute top-2 right-2 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
              onClick={() => onDelete(img.id)}
            >
              <X className="w-3 h-3" />
            </button>
          )}
        </div>
      ))}
    </div>
  );
}
