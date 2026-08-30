import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X } from 'lucide-react';
import SpreadsheetArtifact from './SpreadsheetArtifact';

export type ActiveArtifact =
    | {
          type: 'spreadsheet';
          status: 'draft';
          artifact_uuid: string;
          title: string;
      }
    | {
          type: 'spreadsheet';
          status?: 'committed';
          resource_uuid: string;
          title: string;
      };

interface Props {
    activeArtifact: ActiveArtifact | null;
    onClose: () => void;
}

export default function ArtifactPanel({ activeArtifact, onClose }: Props) {
    return (
        <AnimatePresence>
            {activeArtifact && (
                <motion.div
                    initial={{ width: 0, opacity: 0 }}
                    animate={{ width: '45vw', opacity: 1 }}
                    exit={{ width: 0, opacity: 0 }}
                    transition={{ type: 'spring', damping: 25, stiffness: 200 }}
                    className="h-full border-l border-neutral-200 bg-white shadow-[-10px_0_30px_rgba(0,0,0,0.03)] flex flex-col overflow-hidden relative z-20 min-w-[320px] max-w-[800px]"
                >
                    {/* Header Padrão do Painel */}
                    <div className="absolute top-4 right-4 z-50">
                        <button
                            onClick={onClose}
                            className="p-1.5 bg-white/80 backdrop-blur-sm hover:bg-neutral-100 text-neutral-500 rounded-full transition-colors border border-transparent hover:border-neutral-200 shadow-sm"
                            title="Fechar"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="flex-1 w-full h-full relative">
                        {activeArtifact.type === 'spreadsheet' ? (
                            activeArtifact.status === 'draft' ? (
                                <SpreadsheetArtifact mode="draft" artifactUuid={activeArtifact.artifact_uuid} />
                            ) : (
                                <SpreadsheetArtifact mode="resource" resourceUuid={activeArtifact.resource_uuid} />
                            )
                        ) : (
                            <div className="flex items-center justify-center h-full text-neutral-400">
                                Tipo de artefato não suportado: {activeArtifact.type}
                            </div>
                        )}
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
