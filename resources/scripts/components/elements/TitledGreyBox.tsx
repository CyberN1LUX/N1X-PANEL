import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <div css={tw`rounded-lg shadow-md bg-neutral-700 border border-neutral-600`} className={className}>
        <div css={tw`bg-neutral-800 rounded-t-lg p-3 border-b border-neutral-600`}>
            {typeof title === 'string' ? (
                <p css={tw`text-sm font-semibold uppercase text-neutral-50 flex items-center`}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2 text-primary-400`} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-3 text-neutral-200`}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
