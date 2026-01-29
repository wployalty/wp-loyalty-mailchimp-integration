import React from 'react';

const DeleteIconContainer = ({click, extraCss, title}) => {
    return <div
        className={`flex items-end cursor-pointer  justify-center px-2  rounded ${extraCss}`}
        onClick={click}
    >
        <i className="wlr wlrf-delete color-important text-xl text-red-600 " title={title}></i>
    </div>
};

export default DeleteIconContainer;